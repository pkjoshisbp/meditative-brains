<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'backend_category_id',
    'is_active',
    // Newly added audio + marketing fields
    'short_description',
    'sale_price',
    'tags',
    'sort_order',
    'is_featured',
    'cover_image_path',
    'meta_title',
    'meta_description',
    'meta_keywords',
    'bg_music_volume',
    'message_repeat_count',
    'repeat_interval',
    'message_interval',
    'fade_in_duration',
    'fade_out_duration',
    'enable_silence_padding',
    'silence_start',
    'silence_end',
    'has_background_music',
    'background_music_type',
    'background_music_track',
    'audio_urls',
    'preview_audio_url',
    'slug'
    ];

    protected $casts = [
        'sample_messages' => 'array',
        'price' => 'decimal:2',
        'preview_duration' => 'integer',
        'total_messages_count' => 'integer',
    'is_active' => 'boolean',
    'is_featured' => 'boolean',
    'enable_silence_padding' => 'boolean',
    'has_background_music' => 'boolean',
    'bg_music_volume' => 'float',
    'message_repeat_count' => 'integer',
    'repeat_interval' => 'float',
    'message_interval' => 'float',
    'fade_in_duration' => 'float',
    'fade_out_duration' => 'float',
    'silence_start' => 'float',
    'silence_end' => 'float'
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

    // Ensure slug present when saving (basic auto-generation if missing)
    protected static function booted()
    {
        static::saving(function ($model) {
            if (empty($model->slug) && !empty($model->name)) {
                $base = Str::slug($model->name);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->where('id', '!=', $model->id)->exists()) {
                    $slug = $base.'-'.$model->id.'-'.$i;
                    $i++;
                }
                $model->slug = $slug;
            }
        });
    }
}
