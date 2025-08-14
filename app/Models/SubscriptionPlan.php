<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class SubscriptionPlan extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'features',
        'access_rules',
        'includes_music_library',
        'includes_all_tts_categories',
        'included_tts_categories',
        'is_active',
        'is_featured',
        'trial_days',
        'sort_order'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'access_rules' => 'array',
        'included_tts_categories' => 'array',
        'includes_music_library' => 'boolean',
        'includes_all_tts_categories' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Subscriptions using this plan
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_type', 'slug');
    }

    /**
     * Scope for active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured plans
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Check if plan includes music library access
     */
    public function includesMusicLibrary()
    {
        return $this->includes_music_library;
    }

    /**
     * Check if plan includes all TTS categories
     */
    public function includesAllTtsCategories()
    {
        return $this->includes_all_tts_categories;
    }

    /**
     * Get specific TTS categories included in this plan
     */
    public function getIncludedTtsCategories()
    {
        return $this->included_tts_categories ?? [];
    }

    /**
     * Check if a specific TTS category is included
     */
    public function includesTtsCategory($categoryName)
    {
        if ($this->includes_all_tts_categories) {
            return true;
        }

        return in_array($categoryName, $this->getIncludedTtsCategories());
    }
}
