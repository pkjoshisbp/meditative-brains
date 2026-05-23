<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'mobile',
        'password',
        'student_status',
        'student_expires_at',
        'student_verified_at',
        'student_reviewed_at',
        'student_reviewed_by',
        'student_institution',
        'student_id_number',
        'student_document_path',
        'student_document_uploaded_at',
        'student_review_notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'student_expires_at' => 'datetime',
        'student_verified_at' => 'datetime',
        'student_reviewed_at' => 'datetime',
        'student_document_uploaded_at' => 'datetime',
    ];

    /**
     * User's subscriptions
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * User's orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * User's cart items
     */
    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Registered devices
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function trustedLoginDevices()
    {
        return $this->hasMany(TrustedLoginDevice::class);
    }

    public function downloads()
    {
        return $this->hasMany(UserDownload::class);
    }

    public function studentReviewer()
    {
        return $this->belongsTo(User::class, 'student_reviewed_by');
    }

    public function affiliateProfile()
    {
        return $this->hasOne(AffiliateProfile::class);
    }

    public function affiliateClicks()
    {
        return $this->hasMany(AffiliateClick::class, 'visitor_user_id');
    }

    public function affiliateConversions()
    {
        return $this->hasMany(AffiliateConversion::class, 'referred_user_id');
    }

    public function hasStudentPricingAccess(): bool
    {
        if ($this->student_status === 'approved') {
            return true;
        }

        return $this->student_status === 'pending'
            && $this->student_expires_at
            && $this->student_expires_at->isFuture();
    }

    public function studentDocumentUrl(): ?string
    {
        if (! $this->student_document_path) {
            return null;
        }

        return Storage::disk('public')->url($this->student_document_path);
    }

    public function studentStatusLabel(): string
    {
        return match ($this->student_status) {
            'approved' => 'Approved',
            'pending' => 'Pending review',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
            default => 'Not submitted',
        };
    }

    public function isAffiliate(): bool
    {
        return $this->affiliateProfile()->exists();
    }

    public function isAdmin(): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        $adminEmails = config('admin.emails', []);

        if (is_string($adminEmails)) {
            $adminEmails = explode(',', $adminEmails);
        }

        $adminEmails = array_values(array_filter(array_map(
            static fn ($email) => trim(strtolower((string) $email)),
            $adminEmails
        )));

        return $this->email
            && in_array(strtolower($this->email), $adminEmails, true);
    }

    public function hasRole(string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($role === 'admin' && $this->isAdmin()) {
                return true;
            }

            if ($this->role === $role) {
                return true;
            }
        }

        return false;
    }

    public function withinDeviceLimit($deviceUuid = null)
    {
        $limit = $this->device_limit ?? 2;
        $count = $this->devices()->count();
        if ($deviceUuid && $this->devices()->where('device_uuid',$deviceUuid)->exists()) return true; // already registered
        return $count < $limit;
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
    }

    /**
     * Get user's active subscription
     */
    public function getActiveSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();
    }

    /**
     * Check if user has purchased a specific product
     */
    public function hasPurchased($productId)
    {
        return $this->orders()
            ->where('status', 'completed')
            ->whereJsonContains('order_items', ['product_id' => $productId])
            ->exists();
    }

    /**
     * Get user's purchased products
     */
    public function getPurchasedProducts()
    {
        $orderItems = $this->orders()
            ->where('status', 'completed')
            ->pluck('order_items')
            ->flatten(1);

        $productIds = collect($orderItems)->pluck('product_id')->unique();
        
        return Product::whereIn('id', $productIds)->get();
    }

    /**
     * Music access controls
     */
    public function musicAccessControls()
    {
        return $this->hasMany(MusicAccessControl::class);
    }

    /**
     * TTS category access records
     */
    public function ttsCategoryAccess()
    {
        return $this->hasMany(TtsCategoryAccess::class);
    }

    /**
     * Check if user has access to music library
     */
    public function hasMusicLibraryAccess()
    {
        // Check for active subscription that includes music library
        $activeSubscription = $this->getActiveSubscription();
        if ($activeSubscription) {
            $plan = SubscriptionPlan::where('slug', $activeSubscription->plan_type)->first();
            if ($plan && $plan->includesMusicLibrary()) {
                return true;
            }
        }

        // Check for specific music library access
        return $this->musicAccessControls()
            ->where('content_type', 'music')
            ->where('content_identifier', 'all_music')
            ->active()
            ->exists();
    }

    /**
     * Check if user has access to a specific music product
     */
    public function hasMusicProductAccess($productId)
    {
        // Check if user has general music library access
        if ($this->hasMusicLibraryAccess()) {
            return true;
        }

        // Check for specific product purchase
        if ($this->hasPurchased($productId)) {
            return true;
        }

        // Check for specific product access control
        return $this->musicAccessControls()
            ->where('content_type', 'single_product')
            ->where('content_identifier', $productId)
            ->active()
            ->exists();
    }

    /**
     * Check if user has access to a TTS category
     */
    public function hasTtsCategoryAccess($categoryName)
    {
        // Check for active subscription that includes all TTS categories
        $activeSubscription = $this->getActiveSubscription();
        if ($activeSubscription) {
            $plan = SubscriptionPlan::where('slug', $activeSubscription->plan_type)->first();
            if ($plan && $plan->includesAllTtsCategories()) {
                return true;
            }
            if ($plan && $plan->includesTtsCategory($categoryName)) {
                return true;
            }
        }

        // Check for specific category access
        return $this->ttsCategoryAccess()
            ->where('category_name', $categoryName)
            ->active()
            ->exists();
    }

    /**
     * Get all accessible TTS categories for user
     */
    public function getAccessibleTtsCategories()
    {
        $categories = [];

        // Check subscription access
        $activeSubscription = $this->getActiveSubscription();
        if ($activeSubscription) {
            $plan = SubscriptionPlan::where('slug', $activeSubscription->plan_type)->first();
            if ($plan) {
                if ($plan->includesAllTtsCategories()) {
                    // Return all available categories from database
                    return TtsCategory::active()->pluck('name')->toArray();
                } else {
                    $categories = array_merge($categories, $plan->getIncludedTtsCategories());
                }
            }
        }

        // Add individually purchased categories (existing access control system)
        $purchasedCategories = $this->ttsCategoryAccess()
            ->active()
            ->pluck('category_name')
            ->toArray();

        $categories = array_merge($categories, $purchasedCategories);

        // Add categories from TTS product purchases
        $productPurchaseCategories = $this->completedTtsProductPurchases()
            ->with('product')
            ->get()
            ->pluck('product.category')
            ->unique()
            ->filter()
            ->toArray();

        $categories = array_merge($categories, $productPurchaseCategories);

        return array_unique($categories);
    }

    /**
     * Get user's music library access summary
     */
    public function getMusicAccessSummary()
    {
        $summary = [
            'has_full_access' => $this->hasMusicLibraryAccess(),
            'purchased_products' => [],
            'subscription_access' => false,
            'access_expires_at' => null
        ];

        // Check subscription
        $activeSubscription = $this->getActiveSubscription();
        if ($activeSubscription) {
            $plan = SubscriptionPlan::where('slug', $activeSubscription->plan_type)->first();
            if ($plan && $plan->includesMusicLibrary()) {
                $summary['subscription_access'] = true;
                $summary['access_expires_at'] = $activeSubscription->ends_at;
            }
        }

        // Get purchased music products
        $musicProducts = $this->getPurchasedProducts()
            ->filter(function ($product) {
                return in_array($product->audio_type, [
                    'sleep_aid', 'meditation', 'binaural_beats', 
                    'nature_sounds', 'solfeggio'
                ]);
            });

        $summary['purchased_products'] = $musicProducts->pluck('id')->toArray();

        return $summary;
    }

    /**
     * Get user's TTS product purchases
     */
    public function ttsProductPurchases()
    {
        return $this->hasMany(TtsProductPurchase::class);
    }

    /**
     * Get user's completed TTS product purchases
     */
    public function completedTtsProductPurchases()
    {
        return $this->ttsProductPurchases()->completed();
    }

    /**
     * Check if user has purchased a specific TTS audio product
     */
    public function hasTtsProductAccess($productId)
    {
        return $this->completedTtsProductPurchases()
            ->where('tts_audio_product_id', $productId)
            ->exists();
    }

    /**
     * Check if user has access to a TTS category (via subscription or product purchase)
     */
    public function hasTtsCategoryAccessExtended($categoryName)
    {
        // Check existing subscription access
        if ($this->hasTtsCategoryAccess($categoryName)) {
            return [
                'has_access' => true,
                'access_type' => 'subscription',
                'source' => 'subscription_or_trial'
            ];
        }

        // Check individual product purchases in this category
        $hasPurchasedProduct = $this->completedTtsProductPurchases()
            ->whereHas('product', function($query) use ($categoryName) {
                $query->where('category', $categoryName);
            })
            ->exists();

        if ($hasPurchasedProduct) {
            return [
                'has_access' => true,
                'access_type' => 'product_purchase',
                'source' => 'individual_product'
            ];
        }

        return [
            'has_access' => false,
            'access_type' => 'none',
            'source' => 'no_access'
        ];
    }

    /**
     * Get user's TTS access summary
     */
    public function getTtsAccessSummary()
    {
        return [
            'subscription_access' => $this->getActiveSubscription() ? true : false,
            'purchased_products' => $this->completedTtsProductPurchases()->count(),
            'accessible_categories' => $this->getAccessibleTtsCategories(),
            'trial_access' => $this->ttsCategoryAccess()
                ->where('access_type', 'trial')
                ->active()
                ->exists()
        ];
    }
}
