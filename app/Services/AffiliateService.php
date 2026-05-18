<?php

namespace App\Services;

use App\Models\AffiliateClick;
use App\Models\AffiliateConversion;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProfile;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\TtsProductPurchase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AffiliateService
{
    public function apply(User $user, array $data): AffiliateProfile
    {
        $profile = AffiliateProfile::firstOrNew(['user_id' => $user->id]);

        $profile->fill([
            'referral_code' => $profile->referral_code ?: $this->generateReferralCode($user),
            'status' => $profile->exists && $profile->status === 'active' ? 'active' : 'pending',
            'commission_rate' => $profile->commission_rate ?: 10.00,
            'payout_email' => $data['payout_email'] ?? $profile->payout_email,
            'application_notes' => $data['application_notes'] ?? null,
        ]);

        $profile->save();

        return $profile;
    }

    public function currentAffiliateProfile(): ?AffiliateProfile
    {
        $profileId = session('affiliate_profile_id');

        if (! $profileId) {
            return null;
        }

        $profile = AffiliateProfile::find($profileId);

        if (! $profile || ! $profile->isActive()) {
            $this->clearAttribution();
            return null;
        }

        return $profile;
    }

    public function currentAffiliateClick(): ?AffiliateClick
    {
        $clickId = session('affiliate_click_id');

        return $clickId ? AffiliateClick::find($clickId) : null;
    }

    public function captureReferral(Request $request): void
    {
        $code = trim((string) $request->query('aff', ''));

        if ($code === '') {
            return;
        }

        $profile = AffiliateProfile::where('referral_code', $code)
            ->where('status', 'active')
            ->first();

        if (! $profile) {
            return;
        }

        if (Auth::check() && $profile->user_id === Auth::id()) {
            $this->clearAttribution();
            return;
        }

        $click = AffiliateClick::create([
            'affiliate_profile_id' => $profile->id,
            'visitor_user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
            'landing_url' => substr($request->fullUrl(), 0, 2048),
            'referrer_url' => substr((string) $request->headers->get('referer', ''), 0, 2048) ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 65535) ?: null,
        ]);

        session([
            'affiliate_profile_id' => $profile->id,
            'affiliate_click_id' => $click->id,
            'affiliate_referral_code' => $profile->referral_code,
        ]);
    }

    public function clearAttribution(): void
    {
        session()->forget([
            'affiliate_profile_id',
            'affiliate_click_id',
            'affiliate_referral_code',
        ]);
    }

    public function attributionData(float $grossAmount, ?User $customer = null): array
    {
        $profile = $this->currentAffiliateProfile();

        if (! $profile) {
            return [];
        }

        if ($customer && $profile->user_id === $customer->id) {
            return [];
        }

        $rate = (float) $profile->commission_rate;
        $amount = round(($grossAmount * $rate) / 100, 2);

        return [
            'affiliate_profile_id' => $profile->id,
            'affiliate_click_id' => session('affiliate_click_id'),
            'affiliate_referral_code' => $profile->referral_code,
            'affiliate_commission_rate' => $rate,
            'affiliate_commission_amount' => $amount,
        ];
    }

    public function recordOrderConversion(Order $order, string $currency = 'USD'): ?AffiliateConversion
    {
        if (! $order->affiliate_profile_id) {
            return null;
        }

        $conversion = AffiliateConversion::firstOrCreate(
            ['order_id' => $order->id],
            [
                'affiliate_profile_id' => $order->affiliate_profile_id,
                'affiliate_click_id' => $order->affiliate_click_id,
                'referred_user_id' => $order->user_id,
                'conversion_type' => 'product_order',
                'currency' => $currency,
                'gross_amount' => $order->total_amount,
                'commission_rate' => $order->affiliate_commission_rate,
                'commission_amount' => $order->affiliate_commission_amount,
                'status' => 'approved',
                'approved_at' => now(),
            ]
        );

        $this->markClickConverted($order->affiliate_click_id);

        return $conversion;
    }

    public function recordSubscriptionConversion(Subscription $subscription, string $currency = 'USD'): ?AffiliateConversion
    {
        if (! $subscription->affiliate_profile_id) {
            return null;
        }

        $conversion = AffiliateConversion::firstOrCreate(
            ['subscription_id' => $subscription->id],
            [
                'affiliate_profile_id' => $subscription->affiliate_profile_id,
                'affiliate_click_id' => $subscription->affiliate_click_id,
                'referred_user_id' => $subscription->user_id,
                'conversion_type' => 'subscription',
                'currency' => $currency,
                'gross_amount' => $subscription->price,
                'commission_rate' => $subscription->affiliate_commission_rate,
                'commission_amount' => $subscription->affiliate_commission_amount,
                'status' => 'approved',
                'approved_at' => now(),
            ]
        );

        $this->markClickConverted($subscription->affiliate_click_id);

        return $conversion;
    }

    public function recordTtsConversion(TtsProductPurchase $purchase, string $currency = 'USD'): ?AffiliateConversion
    {
        if (! $purchase->affiliate_profile_id) {
            return null;
        }

        $conversion = AffiliateConversion::firstOrCreate(
            ['tts_product_purchase_id' => $purchase->id],
            [
                'affiliate_profile_id' => $purchase->affiliate_profile_id,
                'affiliate_click_id' => $purchase->affiliate_click_id,
                'referred_user_id' => $purchase->user_id,
                'conversion_type' => 'tts_product',
                'currency' => $currency,
                'gross_amount' => $purchase->amount,
                'commission_rate' => $purchase->affiliate_commission_rate,
                'commission_amount' => $purchase->affiliate_commission_amount,
                'status' => 'approved',
                'approved_at' => now(),
            ]
        );

        $this->markClickConverted($purchase->affiliate_click_id);

        return $conversion;
    }

    public function payOutstanding(AffiliateProfile $profile, ?string $reference = null, ?string $notes = null): ?AffiliatePayout
    {
        $conversions = $profile->conversions()
            ->where('status', 'approved')
            ->whereNull('affiliate_payout_id')
            ->get();

        if ($conversions->isEmpty()) {
            return null;
        }

        $currency = (string) ($conversions->first()->currency ?? 'USD');
        $total = (float) $conversions->sum('commission_amount');

        $payout = AffiliatePayout::create([
            'affiliate_profile_id' => $profile->id,
            'amount' => $total,
            'currency' => $currency,
            'status' => 'paid',
            'reference' => $reference,
            'notes' => $notes,
            'paid_at' => now(),
            'recorded_by' => Auth::id(),
        ]);

        foreach ($conversions as $conversion) {
            $conversion->update([
                'status' => 'paid',
                'paid_at' => now(),
                'affiliate_payout_id' => $payout->id,
            ]);
        }

        return $payout;
    }

    private function generateReferralCode(User $user): string
    {
        $base = Str::upper(Str::slug($user->name ?: 'affiliate', ''));
        $base = substr($base, 0, 8) ?: 'AFFIL';

        do {
            $code = $base . Str::upper(Str::random(4));
        } while (AffiliateProfile::where('referral_code', $code)->exists());

        return $code;
    }

    private function markClickConverted(?int $clickId): void
    {
        if (! $clickId) {
            return;
        }

        AffiliateClick::whereKey($clickId)
            ->whereNull('converted_at')
            ->update(['converted_at' => now()]);
    }
}