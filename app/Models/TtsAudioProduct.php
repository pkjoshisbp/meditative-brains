<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TtsAudioProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'language',
        'price',
        'preview_duration',
        'background_music_url',
        'cover_image_url',
        'sample_messages',
        'total_messages_count',
        'is_active'
    ];

    protected $casts = [
        'sample_messages' => 'array',
        'price' => 'decimal:2',
        'preview_duration' => 'integer',
        'total_messages_count' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Get the category this product belongs to
     */
    public function ttsCategory()
    {
        return $this->belongsTo(TtsCategory::class, 'category', 'name');
    }

    /**
     * Get all purchases of this product
     */
    public function purchases()
    {
        return $this->hasMany(TtsProductPurchase::class);
    }

    /**
     * Get completed purchases of this product
     */
    public function completedPurchases()
    {
        return $this->purchases()->where('status', 'completed');
    }

    /**
     * Check if a specific user has purchased this product
     */
    public function isPurchasedByUser($userId)
    {
        return $this->completedPurchases()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get purchase record for a specific user
     */
    public function getPurchaseByUser($userId)
    {
        return $this->completedPurchases()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for products by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for products by language
     */
    public function scopeByLanguage($query, $language)
    {
        return $query->where('language', $language);
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Check if product has preview samples
     */
    public function hasPreviewSamples()
    {
        return !empty($this->sample_messages) && count($this->sample_messages) > 0;
    }

    /**
     * Get random sample message for preview
     */
    public function getRandomSampleMessage()
    {
        if (!$this->hasPreviewSamples()) {
            return null;
        }

        return $this->sample_messages[array_rand($this->sample_messages)];
    }
}
