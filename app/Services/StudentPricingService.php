<?php

namespace App\Services;

use App\Helpers\CurrencyHelper;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\TtsAudioProduct;
use App\Models\User;

class StudentPricingService
{
    public function userQualifies(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $this->refreshUserStatus($user);

        return in_array($user->student_status, ['approved', 'pending'], true);
    }

    public function refreshUserStatus(?User $user): ?User
    {
        if (! $user) {
            return null;
        }

        if ($user->student_status === 'pending' && $user->student_expires_at && $user->student_expires_at->isPast()) {
            $user->forceFill(['student_status' => 'expired'])->save();
            $user->refresh();
        }

        return $user;
    }

    public function forRegularProduct(Product $product, ?User $user): array
    {
        $baseUsd = (float) ($product->price ?? 0);
        $baseInr = (float) (($product->inr_price ?? null) ?: ($baseUsd * CurrencyHelper::USD_TO_INR));
        $publicUsd = (float) ($product->sale_price ?? $product->price ?? 0);
        $publicInr = $product->sale_price !== null
            ? (float) (($product->inr_sale_price ?? null) ?: ($publicUsd * CurrencyHelper::USD_TO_INR))
            : $baseInr;

        return $this->buildPriceData(
            $baseUsd,
            $baseInr,
            $publicUsd,
            $publicInr,
            $product->student_price,
            $product->student_inr_price,
            $user
        );
    }

    public function forSubscriptionPlan(SubscriptionPlan $plan, ?User $user, string $billingInterval = 'monthly'): array
    {
        $billingInterval = strtolower($billingInterval);

        if ($billingInterval === 'yearly') {
            $monthlyUsd = (float) ($plan->price ?? 0);
            $monthlyInr = $plan->inr_price !== null
                ? (float) $plan->inr_price
                : $monthlyUsd * CurrencyHelper::USD_TO_INR;

            $baseUsd = $plan->yearly_price !== null
                ? (float) $plan->yearly_price
                : $monthlyUsd * 10;
            $baseInr = $plan->yearly_inr_price !== null
                ? (float) $plan->yearly_inr_price
                : ($plan->inr_price !== null ? $monthlyInr * 10 : $baseUsd * CurrencyHelper::USD_TO_INR);

            return $this->buildPriceData(
                $baseUsd,
                $baseInr,
                $baseUsd,
                $baseInr,
                $plan->yearly_student_price ?? $plan->student_price,
                $plan->yearly_student_inr_price ?? $plan->student_inr_price,
                $user
            );
        }

        $baseUsd = (float) ($plan->price ?? 0);
        $baseInr = $plan->inr_price !== null
            ? (float) $plan->inr_price
            : $baseUsd * CurrencyHelper::USD_TO_INR;

        return $this->buildPriceData(
            $baseUsd,
            $baseInr,
            $baseUsd,
            $baseInr,
            $plan->student_price,
            $plan->student_inr_price,
            $user
        );
    }

    public function forTtsProduct(TtsAudioProduct $product, string $productType, ?User $user): array
    {
        [$baseUsd, $baseInr, $publicUsd, $publicInr, $studentUsd, $studentInr] = match ($productType) {
            'ebook_pdf' => [
                (float) ($product->pdf_price ?? 4.90),
                (float) ($product->pdf_price_inr ?? (($product->pdf_price ?? 4.90) * CurrencyHelper::USD_TO_INR)),
                (float) ($product->pdf_price ?? 4.90),
                (float) ($product->pdf_price_inr ?? (($product->pdf_price ?? 4.90) * CurrencyHelper::USD_TO_INR)),
                $product->student_pdf_price,
                $product->student_pdf_price_inr,
            ],
            'ebook_bundle' => [
                (float) ($product->bundle_price ?? 10.00),
                (float) ($product->bundle_price_inr ?? (($product->bundle_price ?? 10.00) * CurrencyHelper::USD_TO_INR)),
                (float) ($product->bundle_price ?? 10.00),
                (float) ($product->bundle_price_inr ?? (($product->bundle_price ?? 10.00) * CurrencyHelper::USD_TO_INR)),
                $product->student_bundle_price,
                $product->student_bundle_price_inr,
            ],
            default => [
                (float) ($product->price ?? 0),
                (float) ($product->inr_price ?? (($product->price ?? 0) * CurrencyHelper::USD_TO_INR)),
                (float) ($product->sale_price ?? $product->price ?? 0),
                (float) ($product->inr_sale_price ?? $product->inr_price ?? (($product->sale_price ?? $product->price ?? 0) * CurrencyHelper::USD_TO_INR)),
                $product->student_audio_price,
                $product->student_audio_price_inr,
            ],
        };

        return $this->buildPriceData($baseUsd, $baseInr, $publicUsd, $publicInr, $studentUsd, $studentInr, $user);
    }

    private function buildPriceData(
        float $baseUsd,
        float $baseInr,
        float $publicUsd,
        float $publicInr,
        mixed $studentUsd,
        mixed $studentInr,
        ?User $user
    ): array {
        $eligible = $this->userQualifies($user);
        $hasStudentOverride = $studentUsd !== null || $studentInr !== null;

        $studentUsdValue = $studentUsd !== null
            ? (float) $studentUsd
            : null;
        $studentInrValue = $studentInr !== null
            ? (float) $studentInr
            : ($studentUsdValue !== null ? $studentUsdValue * CurrencyHelper::USD_TO_INR : null);

        $studentApplied = $eligible && $hasStudentOverride;

        return [
            'student_applied' => $studentApplied,
            'student_available' => $hasStudentOverride,
            'student_eligible' => $eligible,
            'student_status' => $user?->student_status,
            'base_usd' => round($baseUsd, 2),
            'base_inr' => round($baseInr, 2),
            'public_usd' => round($publicUsd, 2),
            'public_inr' => round($publicInr, 2),
            'student_usd' => $studentUsdValue !== null ? round($studentUsdValue, 2) : null,
            'student_inr' => $studentInrValue !== null ? round($studentInrValue, 2) : null,
            'final_usd' => round($studentApplied ? $studentUsdValue : $publicUsd, 2),
            'final_inr' => round($studentApplied ? $studentInrValue : $publicInr, 2),
            'audience' => $studentApplied ? 'student' : 'public',
        ];
    }
}
