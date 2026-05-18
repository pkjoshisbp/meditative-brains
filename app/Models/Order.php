<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'affiliate_profile_id',
        'affiliate_click_id',
        'affiliate_referral_code',
        'affiliate_commission_rate',
        'affiliate_commission_amount',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'payment_method',
        'payment_status',
        'payment_transaction_id',
        'billing_details',
        'order_items',
        'notes',
        'completed_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'affiliate_commission_rate' => 'decimal:2',
        'affiliate_commission_amount' => 'decimal:2',
        'billing_details' => 'array',
        'order_items' => 'array',
        'completed_at' => 'datetime',
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

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function generateOrderNumber()
    {
        return 'ORD-' . date('Y') . '-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            // Generate order number after the ID is available
            $order->order_number = 'ORD-' . date('Y') . '-' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        });
    }
}
