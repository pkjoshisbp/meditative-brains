<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TtsCategoryAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_name',
        'access_type',
        'granted_at',
        'expires_at',
        'is_active',
        'purchase_reference',
        'price_paid'
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'price_paid' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the access is currently valid
     */
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        // If no expiry date, it's lifetime access
        if (!$this->expires_at) {
            return true;
        }

        return $this->expires_at > now();
    }

    /**
     * Check if access is expired
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at < now();
    }

    /**
     * Scope for active access records
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for specific category
     */
    public function scopeForCategory($query, $categoryName)
    {
        return $query->where('category_name', $categoryName);
    }
}
