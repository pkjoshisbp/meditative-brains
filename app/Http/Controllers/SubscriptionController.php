<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Services\AccessControlService;
use App\Services\AffiliateService;
use App\Services\CcAvenueService;
use App\Services\PayPalService;
use App\Services\PromoCodeService;
use App\Services\StudentPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(
        private StudentPricingService $studentPricing,
        private PromoCodeService $promoCodeService,
        private PayPalService $payPalService,
        private AccessControlService $accessControlService,
        private AffiliateService $affiliateService,
        private CcAvenueService $ccAvenueService,
    ) {
    }

    public function index(Request $request)
    {
        $billingInterval = $this->normaliseBillingInterval($request->query('interval', 'monthly'));

        $plans = $this->basePlans()->map(function (SubscriptionPlan $plan) use ($billingInterval, $request) {
            $pricing = $this->studentPricing->forSubscriptionPlan($plan, $request->user(), $billingInterval);

            return [
                'plan' => $plan,
                'pricing' => $pricing,
            ];
        });

        return view('pages.subscription', [
            'billingInterval' => $billingInterval,
            'plans' => $plans,
        ]);
    }

    public function showCheckout(Request $request)
    {
        $request->validate([
            'plan' => 'required|exists:subscription_plans,id',
            'interval' => 'required|in:monthly,yearly',
            'promo' => 'nullable|string|max:50',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->integer('plan'));
        $billingInterval = $this->normaliseBillingInterval($request->query('interval', 'monthly'));
        $promoInput = strtoupper(trim((string) $request->query('promo', '')));
        $promoCode = $this->promoCodeService->findUsableCode($promoInput);
        $pricing = $this->studentPricing->forSubscriptionPlan($plan, $request->user(), $billingInterval);

        $finalUsd = $pricing['final_usd'];
        $finalInr = $pricing['final_inr'];
        $discountUsd = 0.0;
        $discountInr = 0.0;

        if ($promoCode) {
            $discountUsd = $promoCode->discountAmount($finalUsd, 'USD');
            $discountInr = $promoCode->discountAmount($finalInr, 'INR');
            $finalUsd = $promoCode->applyDiscount($finalUsd, 'USD');
            $finalInr = $promoCode->applyDiscount($finalInr, 'INR');
        }

        return view('pages.subscription-checkout', [
            'plan' => $plan,
            'billingInterval' => $billingInterval,
            'pricing' => $pricing,
            'promoInput' => $promoInput,
            'promoCode' => $promoCode,
            'finalUsd' => $finalUsd,
            'finalInr' => $finalInr,
            'discountUsd' => $discountUsd,
            'discountInr' => $discountInr,
        ]);
    }

    public function checkout(Request $request)
    {
        if ($this->usesRazorpayCheckout()) {
            return redirect()->route('subscription.checkout.show', [
                'plan' => $request->input('plan_id'),
                'interval' => $request->input('billing_interval', 'monthly'),
                'promo' => $request->input('promo_code'),
            ])->with('error', 'Use the Razorpay button to complete INR checkout.');
        }

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_interval' => 'required|in:monthly,yearly',
            'promo_code' => 'nullable|string|max:50',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->integer('plan_id'));
        $billingInterval = $this->normaliseBillingInterval($request->string('billing_interval')->toString());
        $promoCode = $this->promoCodeService->requireUsableCode($request->input('promo_code'));

        $returnParameters = [
            'plan' => $plan->id,
            'interval' => $billingInterval,
        ];

        if ($promoCode) {
            $returnParameters['promo'] = $promoCode->code;
        }

        $result = $this->payPalService->createSubscriptionOrder(
            $request->user(),
            $plan,
            $billingInterval,
            $promoCode,
            route('subscription.success', $returnParameters),
            route('subscription.cancel', [
                'plan' => $plan->id,
                'interval' => $billingInterval,
                'promo' => $promoCode?->code,
            ])
        );

        if (! ($result['success'] ?? false) || empty($result['approval_url'])) {
            return redirect()->route('subscription.checkout.show', [
                'plan' => $plan->id,
                'interval' => $billingInterval,
                'promo' => $promoCode?->code,
            ])
                ->with('error', $result['error'] ?? 'Unable to start subscription checkout right now.');
        }

        session([
            'subscription_checkout_plan_id' => $plan->id,
            'subscription_checkout_interval' => $billingInterval,
            'subscription_checkout_promo_code' => $promoCode?->code,
        ]);

        return redirect()->away($result['approval_url']);
    }

    public function createRazorpayOrder(Request $request)
    {
        if (! $this->usesRazorpayCheckout()) {
            return response()->json(['message' => 'Razorpay checkout is available only for India / INR.'], 422);
        }

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_interval' => 'required|in:monthly,yearly',
            'promo_code' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if ($user->hasActiveSubscription()) {
            return response()->json(['message' => 'You already have an active subscription.'], 400);
        }

        $plan = SubscriptionPlan::findOrFail($request->integer('plan_id'));
        $billingInterval = $this->normaliseBillingInterval($request->string('billing_interval')->toString());
        $promoCodeValue = strtoupper(trim((string) $request->input('promo_code', '')));
        $promoCode = $this->promoCodeService->findUsableCode($promoCodeValue);
        $pricing = $this->studentPricing->forSubscriptionPlan($plan, $user, $billingInterval);
        $amountInr = $promoCode
            ? $promoCode->applyDiscount($pricing['final_inr'], 'INR')
            : $pricing['final_inr'];
        $affiliateData = $this->affiliateService->attributionData((float) $amountInr, $user);

        try {
            $api = new \Razorpay\Api\Api(
                config('razorpay.key_id'),
                config('razorpay.key_secret')
            );

            $order = $api->order->create([
                'amount' => (int) round($amountInr * 100),
                'currency' => 'INR',
                'receipt' => 'sub_' . $plan->id . '_' . now()->timestamp,
                'payment_capture' => 1,
                'notes' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                    'billing_interval' => $billingInterval,
                    'promo_code' => $promoCode?->code,
                ],
            ]);

            session([
                'subscription_razorpay_affiliate_data' => $affiliateData,
            ]);

            return response()->json([
                'order_id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'key_id' => config('razorpay.key_id'),
                'plan_name' => $plan->name,
                'description' => ucfirst($billingInterval) . ' subscription',
            ]);
        } catch (\Throwable $e) {
            Log::error('Razorpay subscription order creation failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to start Razorpay checkout right now.'], 500);
        }
    }

    public function startCcavenueCheckout(Request $request)
    {
        if (! $this->usesRazorpayCheckout()) {
            return redirect()->route('subscription')->with('error', 'CCAvenue checkout is available only for India / INR orders.');
        }

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_interval' => 'required|in:monthly,yearly',
            'promo_code' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasActiveSubscription()) {
            return redirect()->route('account.dashboard')->with('message', 'You already have an active subscription.');
        }

        $plan = SubscriptionPlan::findOrFail($request->integer('plan_id'));
        $billingInterval = $this->normaliseBillingInterval($request->string('billing_interval')->toString());
        $promoCodeValue = strtoupper(trim((string) $request->input('promo_code', '')));
        $promoCode = $promoCodeValue !== ''
            ? $this->promoCodeService->findUsableCode($promoCodeValue)
            : null;
        $pricing = $this->studentPricing->forSubscriptionPlan($plan, $user, $billingInterval);
        $amountInr = $promoCode
            ? $promoCode->applyDiscount($pricing['final_inr'], 'INR')
            : $pricing['final_inr'];
        $affiliateData = $this->affiliateService->attributionData((float) $amountInr, $user);
        $orderId = 'sub_' . $user->id . '_' . Str::upper(Str::random(10));

        Cache::put('ccavenue.subscription.' . $orderId, [
            'affiliate' => $affiliateData,
        ], now()->addHour());

        try {
            $checkout = $this->ccAvenueService->buildCheckoutPayload([
                'merchant_id' => $this->ccAvenueService->merchantId(),
                'order_id' => $orderId,
                'currency' => 'INR',
                'amount' => $this->ccAvenueService->formatAmount((float) $amountInr),
                'redirect_url' => route('subscription.checkout.ccavenue.response'),
                'cancel_url' => route('subscription.checkout.ccavenue.response'),
                'language' => 'EN',
                'billing_name' => $user->name,
                'billing_email' => $user->email,
                'billing_tel' => $user->mobile,
                'merchant_param1' => 'subscription',
                'merchant_param2' => (string) $user->id,
                'merchant_param3' => (string) $plan->id,
                'merchant_param4' => $billingInterval,
                'merchant_param5' => $promoCode?->code,
            ]);
        } catch (\Throwable $e) {
            Log::error('CCAvenue subscription checkout failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('subscription.checkout.show', [
                'plan' => $plan->id,
                'interval' => $billingInterval,
                'promo' => $promoCode?->code,
            ])->with('error', 'Unable to start checkout right now.');
        }

        return view('payments.ccavenue-redirect', [
            'gatewayUrl' => $checkout['gateway_url'],
            'encRequest' => $checkout['enc_request'],
            'accessCode' => $checkout['access_code'],
        ]);
    }

    public function handleCcavenueResponse(Request $request)
    {
        $response = $this->ccAvenueService->decryptResponse($request->input('encResp'));
        $orderId = (string) ($response['order_id'] ?? '');
        $userId = (int) ($response['merchant_param2'] ?? 0);

        if ($userId > 0) {
            Auth::loginUsingId($userId);
            $request->session()->regenerate();
        }

        $status = strtoupper((string) ($response['order_status'] ?? 'FAILED'));
        if ($orderId === '' || $status !== 'SUCCESS') {
            return redirect()->route('subscription')->with('error', 'Payment was not completed.');
        }

        $user = $userId > 0 ? Auth::user() : null;
        if (! $user) {
            return redirect()->route('login')->with('error', 'Unable to restore your session after payment.');
        }

        if ($user->hasActiveSubscription()) {
            return redirect()->route('account.dashboard')->with('success', 'Your subscription is already active.');
        }

        $trackingId = (string) ($response['tracking_id'] ?? $orderId);
        $existingSubscription = Subscription::query()
            ->where('payment_method', 'ccavenue')
            ->where('stripe_subscription_id', $trackingId)
            ->first();

        if ($existingSubscription) {
            return redirect()->route('account.dashboard')->with('success', 'Your subscription is already active.');
        }

        $plan = SubscriptionPlan::findOrFail((int) ($response['merchant_param3'] ?? 0));
        $billingInterval = $this->normaliseBillingInterval((string) ($response['merchant_param4'] ?? 'monthly'));
        $promoCodeValue = strtoupper(trim((string) ($response['merchant_param5'] ?? '')));
        $promoCode = $promoCodeValue !== ''
            ? PromoCode::where('code', $promoCodeValue)->first()
            : null;
        $cached = Cache::pull('ccavenue.subscription.' . $orderId, []);

        DB::beginTransaction();

        try {
            $subscription = $this->activateCcavenueSubscription(
                $user,
                $plan,
                $billingInterval,
                $trackingId,
                $promoCode,
                $cached['affiliate'] ?? []
            );

            if ($promoCode) {
                $this->promoCodeService->markRedeemed($promoCode);
            }

            DB::commit();

            return redirect()->route('account.dashboard')->with('success', 'Subscription activated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('CCAvenue subscription verification failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('subscription.checkout.show', [
                'plan' => $plan->id,
                'interval' => $billingInterval,
                'promo' => $promoCodeValue,
            ])->with('error', 'Unable to activate subscription after payment.');
        }
    }

    public function verifyRazorpayPayment(Request $request)
    {
        if (! $this->usesRazorpayCheckout()) {
            return response()->json(['success' => false, 'message' => 'Razorpay checkout is available only for India / INR.'], 422);
        }

        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_interval' => 'required|in:monthly,yearly',
            'promo_code' => 'nullable|string|max:50',
        ]);

        $expectedSignature = hash_hmac(
            'sha256',
            $request->string('razorpay_order_id')->toString() . '|' . $request->string('razorpay_payment_id')->toString(),
            (string) config('razorpay.key_secret')
        );

        if (! hash_equals($expectedSignature, $request->string('razorpay_signature')->toString())) {
            return response()->json(['success' => false, 'message' => 'Payment verification failed.'], 422);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        if ($user->hasActiveSubscription()) {
            return response()->json([
                'success' => true,
                'redirect' => route('account.dashboard'),
            ]);
        }

        $existingSubscription = Subscription::query()
            ->where('payment_method', 'razorpay')
            ->where('stripe_subscription_id', $request->string('razorpay_payment_id')->toString())
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'success' => true,
                'redirect' => route('account.dashboard'),
            ]);
        }

        $plan = SubscriptionPlan::findOrFail($request->integer('plan_id'));
        $billingInterval = $this->normaliseBillingInterval($request->string('billing_interval')->toString());
        $promoCodeValue = strtoupper(trim((string) $request->input('promo_code', '')));
        $promoCode = $promoCodeValue !== ''
            ? $this->promoCodeService->requireUsableCode($promoCodeValue)
            : null;

        DB::beginTransaction();

        try {
            $subscription = $this->activateRazorpaySubscription(
                $user,
                $plan,
                $billingInterval,
                $request->string('razorpay_payment_id')->toString(),
                $promoCode
            );

            if ($promoCode) {
                $this->promoCodeService->markRedeemed($promoCode);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'redirect' => route('account.dashboard'),
                'message' => 'Subscription activated successfully.',
                'subscription_id' => $subscription->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Razorpay subscription verification failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'razorpay_payment_id' => $request->string('razorpay_payment_id')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Unable to activate subscription after payment.'], 500);
        }
    }

    public function success(Request $request)
    {
        if (! $request->filled('token')) {
            return redirect()->route('subscription')->with('error', 'Payment verification failed.');
        }

        $planId = (int) session('subscription_checkout_plan_id', $request->integer('plan'));
        $billingInterval = $this->normaliseBillingInterval((string) session('subscription_checkout_interval', $request->query('interval', 'monthly')));
        $promoCodeValue = (string) session('subscription_checkout_promo_code', $request->query('promo', ''));
        $promoCode = $promoCodeValue !== ''
            ? PromoCode::where('code', strtoupper($promoCodeValue))->first()
            : null;

        $plan = SubscriptionPlan::findOrFail($planId);
        $result = $this->payPalService->processSubscriptionPurchase(
            (string) $request->query('token', ''),
            $request->user(),
            $plan,
            $billingInterval,
            $promoCode
        );

        session()->forget([
            'subscription_checkout_plan_id',
            'subscription_checkout_interval',
            'subscription_checkout_promo_code',
        ]);

        if ($promoCode && ($result['success'] ?? false)) {
            $this->promoCodeService->markRedeemed($promoCode);
        }

        return ($result['success'] ?? false)
            ? redirect()->route('account.dashboard')->with('success', 'Subscription activated successfully.')
            : redirect()->route('subscription.checkout.show', [
                'plan' => $plan->id,
                'interval' => $billingInterval,
                'promo' => $promoCodeValue,
            ])->with('error', $result['error'] ?? 'Payment verification failed.');
    }

    public function cancel(Request $request)
    {
        session()->forget([
            'subscription_checkout_plan_id',
            'subscription_checkout_interval',
            'subscription_checkout_promo_code',
        ]);

        return redirect()->route('subscription.checkout.show', [
            'plan' => $request->query('plan'),
            'interval' => $this->normaliseBillingInterval($request->query('interval', 'monthly')),
            'promo' => $request->query('promo'),
        ])->with('message', 'Subscription checkout was cancelled.');
    }

    private function basePlans(): Collection
    {
        $plans = SubscriptionPlan::active()
            ->where('billing_cycle', '!=', 'yearly')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        if ($plans->isEmpty()) {
            return SubscriptionPlan::active()
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get();
        }

        return $plans;
    }

    private function normaliseBillingInterval(string $billingInterval): string
    {
        return strtolower($billingInterval) === 'yearly' ? 'yearly' : 'monthly';
    }

    private function usesRazorpayCheckout(): bool
    {
        return session('user_currency') === 'INR' || session('payment_gateway') === 'razorpay';
    }

    private function activateRazorpaySubscription(
        $user,
        SubscriptionPlan $plan,
        string $billingInterval,
        string $paymentId,
        ?PromoCode $promoCode = null
    ): Subscription {
        $startsAt = now();
        $endsAt = $billingInterval === 'yearly'
            ? $startsAt->copy()->addYear()
            : $startsAt->copy()->addMonth();

        $price = $this->studentPricing->forSubscriptionPlan($plan, $user, $billingInterval)['final_inr'];
        if ($promoCode) {
            $price = $promoCode->applyDiscount($price, 'INR');
        }

        $affiliateData = session('subscription_razorpay_affiliate_data', []);
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'affiliate_profile_id' => $affiliateData['affiliate_profile_id'] ?? null,
            'affiliate_click_id' => $affiliateData['affiliate_click_id'] ?? null,
            'affiliate_referral_code' => $affiliateData['affiliate_referral_code'] ?? null,
            'affiliate_commission_rate' => $affiliateData['affiliate_commission_rate'] ?? null,
            'affiliate_commission_amount' => $affiliateData['affiliate_commission_amount'] ?? null,
            'plan_type' => $plan->slug,
            'price' => $price,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'payment_method' => 'razorpay',
            'stripe_subscription_id' => $paymentId,
            'auto_renew' => false,
        ]);

        $this->accessControlService->grantSubscriptionAccess(
            $user,
            $plan,
            $endsAt,
            (string) $subscription->id
        );

        $this->affiliateService->recordSubscriptionConversion($subscription, 'INR');
        session()->forget('subscription_razorpay_affiliate_data');

        return $subscription;
    }

    private function activateCcavenueSubscription(
        $user,
        SubscriptionPlan $plan,
        string $billingInterval,
        string $paymentId,
        ?PromoCode $promoCode = null,
        array $affiliateData = []
    ): Subscription {
        $startsAt = now();
        $endsAt = $billingInterval === 'yearly'
            ? $startsAt->copy()->addYear()
            : $startsAt->copy()->addMonth();

        $price = $this->studentPricing->forSubscriptionPlan($plan, $user, $billingInterval)['final_inr'];
        if ($promoCode) {
            $price = $promoCode->applyDiscount($price, 'INR');
        }

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'affiliate_profile_id' => $affiliateData['affiliate_profile_id'] ?? null,
            'affiliate_click_id' => $affiliateData['affiliate_click_id'] ?? null,
            'affiliate_referral_code' => $affiliateData['affiliate_referral_code'] ?? null,
            'affiliate_commission_rate' => $affiliateData['affiliate_commission_rate'] ?? null,
            'affiliate_commission_amount' => $affiliateData['affiliate_commission_amount'] ?? null,
            'plan_type' => $plan->slug,
            'price' => $price,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'payment_method' => 'ccavenue',
            'stripe_subscription_id' => $paymentId,
            'auto_renew' => false,
        ]);

        $this->accessControlService->grantSubscriptionAccess(
            $user,
            $plan,
            $endsAt,
            (string) $subscription->id
        );

        $this->affiliateService->recordSubscriptionConversion($subscription, 'INR');

        return $subscription;
    }
}