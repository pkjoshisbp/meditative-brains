<?php

namespace App\Models;

use App\Helpers\CurrencyHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'valid_until',
        'max_uses',
        'used_count',
        'is_active',
        'applies_to',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($inner) {
                $inner->whereNull('valid_until')->orWhere('valid_until', '>', now());
            });
    }

    public function isUsableFor(string $scope = 'subscriptions'): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->applies_to !== $scope) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function discountAmount(float $amount, string $currency = 'USD'): float
    {
        $amount = max(0, $amount);

        if ($amount === 0.0) {
            return 0.0;
        }

        if ($this->discount_type === 'percent') {
            return round(min($amount, ($amount * (float) $this->discount_value) / 100), 2);
        }

        $flatAmount = (float) $this->discount_value;

        if (strtoupper($currency) === 'INR') {
            $flatAmount *= CurrencyHelper::USD_TO_INR;
        }

        return round(min($amount, $flatAmount), 2);
    }

    public function applyDiscount(float $amount, string $currency = 'USD'): float
    {
        return round(max(0, $amount - $this->discountAmount($amount, $currency)), 2);
    }
}