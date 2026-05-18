<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_profile_id',
        'affiliate_click_id',
        'referred_user_id',
        'order_id',
        'subscription_id',
        'tts_product_purchase_id',
        'affiliate_payout_id',
        'conversion_type',
        'currency',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'status',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function affiliateProfile()
    {
        return $this->belongsTo(AffiliateProfile::class);
    }

    public function affiliateClick()
    {
        return $this->belongsTo(AffiliateClick::class);
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function ttsProductPurchase()
    {
        return $this->belongsTo(TtsProductPurchase::class);
    }

    public function payout()
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }
}