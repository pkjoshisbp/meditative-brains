<?php

namespace App\Helpers;

class CurrencyHelper
{
    /**
     * Exchange rate: $1 USD = ₹100 INR (fixed store rate).
     */
    const USD_TO_INR = 100;

    /**
     * Format a price for display based on the user's detected currency.
     *
     * @param  float|null  $usdPrice
     * @param  float|null  $inrPrice   Explicit INR price stored in DB (takes precedence)
     * @param  string      $currency   'INR' or 'USD'
     * @return string
     */
    public static function format(?float $usdPrice, ?float $inrPrice = null, string $currency = 'USD'): string
    {
        if ($currency === 'INR') {
            $inr = $inrPrice ?? ($usdPrice * self::USD_TO_INR);
            return '₹' . number_format($inr, 0);
        }

        return '$' . number_format($usdPrice ?? 0, 2);
    }

    /**
     * Return the numeric price in the requested currency.
     */
    public static function price(?float $usdPrice, ?float $inrPrice = null, string $currency = 'USD'): float
    {
        if ($currency === 'INR') {
            return $inrPrice ?? ($usdPrice * self::USD_TO_INR);
        }
        return $usdPrice ?? 0;
    }

    /**
     * Detect user currency from session (set by CountryDetect middleware).
     */
    public static function userCurrency(): string
    {
        return session('user_currency', 'USD');
    }

    /**
     * Check if current user is in India.
     */
    public static function isIndia(): bool
    {
        return self::userCurrency() === 'INR';
    }
}
