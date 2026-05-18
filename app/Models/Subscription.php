<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'affiliate_profile_id',
        'affiliate_click_id',
        'affiliate_referral_code',
        'affiliate_commission_rate',
        'affiliate_commission_amount',
        'plan_type',
        'price',
        'status',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'payment_method',
        'stripe_subscription_id',
    'auto_renew',
    'is_trial'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'affiliate_commission_rate' => 'decimal:2',
        'affiliate_commission_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    'is_trial' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function affiliateProfile()
    {
        return $this->belongsTo(AffiliateProfile::class);
    }

    public function affiliateClick()
    {
        return $this->belongsTo(AffiliateClick::class);
    }

    public function isActive()
    {
        return $this->status === 'active' && $this->ends_at > now();
    }

    public function isExpired()
    {
        return $this->ends_at < now();
    }

    public function daysUntilExpiry()
    {
        return now()->diffInDays($this->ends_at, false);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('ends_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('ends_at', '<', now());
    }

    public function scopeByPlan($query, $planType)
    {
        return $query->where('plan_type', $planType);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
