<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TtsProductPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tts_audio_product_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'paypal_order_id',
        'paypal_capture_id',
        'purchased_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'purchased_at' => 'datetime'
    ];

    /**
     * Get the user who made this purchase
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the TTS audio product that was purchased
     */
    public function product()
    {
        return $this->belongsTo(TtsAudioProduct::class, 'tts_audio_product_id');
    }

    /**
     * Get the order associated with this purchase
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope for completed purchases
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending purchases
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for purchases by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if this purchase is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Mark purchase as completed
     */
    public function markAsCompleted($paypalCaptureId = null)
    {
        $this->update([
            'status' => 'completed',
            'paypal_capture_id' => $paypalCaptureId,
            'purchased_at' => now()
        ]);
    }

    /**
     * Mark purchase as failed
     */
    public function markAsFailed()
    {
        $this->update([
            'status' => 'failed'
        ]);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute()
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }
}
