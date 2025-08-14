<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
                    // Return all available categories
                    // You might want to fetch this from your TTS backend or database
                    return [
                        'Self Confidence', 'Positive Attitude', 'Quit Smoking',
                        'Will Power', 'Guided Visualization', 'Hypnotherapy'
                    ];
                } else {
                    $categories = array_merge($categories, $plan->getIncludedTtsCategories());
                }
            }
        }

        // Add individually purchased categories
        $purchasedCategories = $this->ttsCategoryAccess()
            ->active()
            ->pluck('category_name')
            ->toArray();

        $categories = array_merge($categories, $purchasedCategories);

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
}
