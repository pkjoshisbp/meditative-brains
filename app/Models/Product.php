<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory, HasSlug, InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'sale_price',
        'type',
        'audio_type',
        'audio_features',
        'audio_path',
        'preview_duration',
        'preview_file',
        'full_file',
        'tags',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_active',
        'is_featured',
        'sort_order',
        'downloads',
        'average_rating',
        'total_reviews',
        'category_id'
    ];

    protected $casts = [
        'audio_features' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'average_rating' => 'decimal:2',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    public function getCurrentPrice()
    {
        return $this->sale_price ?: $this->price;
    }

    public function hasDiscount()
    {
        return $this->sale_price && $this->sale_price < $this->price;
    }

    public function getDiscountPercentage()
    {
        if (!$this->hasDiscount()) {
            return 0;
        }
        
        return round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->whereFullText(['name', 'description', 'tags'], $search)
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('audio')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/wav', 'audio/ogg']);

        $this->addMediaCollection('preview')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/wav', 'audio/ogg'])
            ->singleFile();
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->performOnCollections('images');

        $this->addMediaConversion('cover')
            ->width(800)
            ->height(600)
            ->performOnCollections('images');
    }

    /**
     * Get secure preview URL
     */
    public function getPreviewUrl($duration = null)
    {
        if (!$this->full_file) {
            return null;
        }

        $audioService = app(\App\Services\AudioSecurityService::class);
        return $audioService->generateSecureUrl(
            $this->full_file,
            $duration ?: $this->preview_duration,
            30 // 30 minutes expiry
        );
    }

    /**
     * Get secure full audio URL (for purchased content)
     */
    public function getFullAudioUrl()
    {
        if (!$this->full_file) {
            return null;
        }

        $audioService = app(\App\Services\AudioSecurityService::class);
        return $audioService->generateSecureUrl(
            $this->full_file,
            null, // No duration limit
            120 // 2 hours expiry
        );
    }

    /**
     * Check if user can access full audio
     */
    public function canUserAccessFull($user)
    {
        if (!$user) {
            return false;
        }

        // Check active subscription
        $hasSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();

        if ($hasSubscription) {
            return true;
        }

        // Check individual purchase
        return $user->orders()
            ->where('status', 'completed')
            ->whereJsonContains('order_items', ['product_id' => $this->id])
            ->exists();
    }

    /**
     * Get audio metadata
     */
    public function getAudioMetadata()
    {
        if (!$this->full_file) {
            return null;
        }

        $audioService = app(\App\Services\AudioSecurityService::class);
        return $audioService->getAudioMetadata($this->full_file);
    }
}
