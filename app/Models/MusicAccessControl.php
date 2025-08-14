<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MusicAccessControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content_type',
        'content_identifier',
        'access_type',
        'granted_at',
        'expires_at',
        'is_active',
        'purchase_reference',
        'metadata'
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
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
     * Get days until expiry (if applicable)
     */
    public function daysUntilExpiry()
    {
        if (!$this->expires_at) {
            return null; // Lifetime access
        }

        return max(0, now()->diffInDays($this->expires_at, false));
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
     * Scope for specific content type
     */
    public function scopeForContentType($query, $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope for specific content identifier
     */
    public function scopeForContent($query, $contentIdentifier)
    {
        return $query->where('content_identifier', $contentIdentifier);
    }
}
