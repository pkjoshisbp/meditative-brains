<?php

namespace App\Services;

use App\Models\PromoCode;
use Illuminate\Validation\ValidationException;

class PromoCodeService
{
    public function findUsableCode(?string $code, string $scope = 'subscriptions'): ?PromoCode
    {
        $normalized = strtoupper(trim((string) $code));

        if ($normalized === '') {
            return null;
        }

        $promoCode = PromoCode::where('code', $normalized)->first();

        if (! $promoCode || ! $promoCode->isUsableFor($scope)) {
            return null;
        }

        return $promoCode;
    }

    public function requireUsableCode(?string $code, string $scope = 'subscriptions'): ?PromoCode
    {
        $normalized = strtoupper(trim((string) $code));

        if ($normalized === '') {
            return null;
        }

        $promoCode = $this->findUsableCode($normalized, $scope);

        if (! $promoCode) {
            throw ValidationException::withMessages([
                'promo_code' => 'That promo code is invalid, expired, or no longer available.',
            ]);
        }

        return $promoCode;
    }

    public function markRedeemed(PromoCode $promoCode): void
    {
        $promoCode->increment('used_count');
    }
}