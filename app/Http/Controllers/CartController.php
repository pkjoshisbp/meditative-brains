<?php

namespace App\Http\Controllers;

use App\Helpers\CurrencyHelper;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Services\AccessControlService;
use App\Services\AffiliateService;
use App\Services\StudentPricingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function __construct(
        private StudentPricingService $studentPricing,
        private AccessControlService $accessControlService,
        private AffiliateService $affiliateService,
    )
    {
    }

    public function index()
    {
        $cartItems = $this->getCartItems();
        $currency = $this->checkoutCurrency();

        $total = $cartItems->sum(function ($item) use ($currency) {
            $unitPrice = $this->resolveItemPriceForCurrency($item, $currency);

            return $unitPrice * ($item->quantity ?? 1);
        });

        return view('cart', compact('cartItems', 'total'));
    }

    public function add(Request $request)
    {
        $request->validate(['product_id' => 'required|integer|exists:products,id']);

        $product = Product::findOrFail($request->product_id);
        $pricing = $this->studentPricing->forRegularProduct($product, auth()->user());

        if (auth()->check()) {
            auth()->user()->cartItems()->updateOrCreate(
                ['product_id' => $product->id],
                ['quantity' => 1, 'price' => $pricing['final_usd']]
            );
            $count = auth()->user()->cartItems()->count();
        } else {
            $cart = session()->get('cart', []);
            $cart[$product->id] = [
                'name'     => $product->name,
                'price'    => $pricing['final_usd'],
                'quantity' => 1,
            ];
            session()->put('cart', $cart);
            $count = count($cart);
        }

        return response()->json([
            'success' => true,
            'message' => "\"{$product->name}\" added to cart!",
            'cart_count' => $count,
            'student_pricing' => $pricing['student_applied'],
        ]);
    }

    public function remove(Request $request)
    {
        $request->validate(['product_id' => 'required|integer']);

        if (auth()->check()) {
            auth()->user()->cartItems()->where('product_id', $request->product_id)->delete();
        } else {
            $cart = session()->get('cart', []);
            unset($cart[$request->product_id]);
            session()->put('cart', $cart);
        }

        return redirect()->route('cart')->with('message', 'Item removed from cart.');
    }

    public function clear()
    {
        if (auth()->check()) {
            auth()->user()->cartItems()->delete();
        } else {
            session()->forget('cart');
        }

        return redirect()->route('cart')->with('message', 'Cart cleared.');
    }

    public function checkout(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $cartItems = $this->getCartItems();
        if ($cartItems->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        if ($this->usesRazorpayCheckout()) {
            return redirect()->route('cart')->with('error', 'Use the Razorpay checkout button for India / INR orders.');
        }

        $orderItems = $this->buildOrderItems($cartItems, 'USD');

        $total = round(collect($orderItems)->sum('total'), 2);
        $affiliateData = $this->affiliateService->attributionData($total, $user);

        try {
            $paypalOrder = $this->createPayPalOrder($orderItems, $total);
            $approvalUrl = collect($paypalOrder['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

            if (! $approvalUrl) {
                throw new \RuntimeException('PayPal approval URL missing.');
            }

            Order::create([
                'user_id' => $user->id,
                'affiliate_profile_id' => $affiliateData['affiliate_profile_id'] ?? null,
                'affiliate_click_id' => $affiliateData['affiliate_click_id'] ?? null,
                'affiliate_referral_code' => $affiliateData['affiliate_referral_code'] ?? null,
                'affiliate_commission_rate' => $affiliateData['affiliate_commission_rate'] ?? null,
                'affiliate_commission_amount' => $affiliateData['affiliate_commission_amount'] ?? null,
                'subtotal' => $total,
                'tax_amount' => 0,
                'total_amount' => $total,
                'status' => 'pending',
                'payment_method' => 'paypal',
                'payment_status' => 'pending',
                'payment_transaction_id' => $paypalOrder['id'],
                'order_items' => $orderItems,
                'notes' => 'Cart checkout',
            ]);

            return redirect()->away($approvalUrl);
        } catch (\Throwable $e) {
            Log::error('Cart PayPal checkout failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('cart')->with('error', 'Unable to start checkout right now.');
        }
    }

    public function createRazorpayOrder(Request $request)
    {
        if (! $this->usesRazorpayCheckout()) {
            return response()->json(['message' => 'Razorpay checkout is available only for India / INR orders.'], 422);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $cartItems = $this->getCartItems();
        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        $orderItems = $this->buildOrderItems($cartItems, 'INR');
        $total = round(collect($orderItems)->sum('total'), 2);
        $affiliateData = $this->affiliateService->attributionData($total, $user);

        try {
            $api = new \Razorpay\Api\Api(
                config('razorpay.key_id'),
                config('razorpay.key_secret')
            );

            $razorpayOrder = $api->order->create([
                'amount' => (int) round($total * 100),
                'currency' => 'INR',
                'receipt' => 'cart_' . $user->id . '_' . now()->timestamp,
                'payment_capture' => 1,
                'notes' => [
                    'user_id' => (string) $user->id,
                    'cart_items' => (string) count($orderItems),
                ],
            ]);

            $order = Order::create([
                'user_id' => $user->id,
                'affiliate_profile_id' => $affiliateData['affiliate_profile_id'] ?? null,
                'affiliate_click_id' => $affiliateData['affiliate_click_id'] ?? null,
                'affiliate_referral_code' => $affiliateData['affiliate_referral_code'] ?? null,
                'affiliate_commission_rate' => $affiliateData['affiliate_commission_rate'] ?? null,
                'affiliate_commission_amount' => $affiliateData['affiliate_commission_amount'] ?? null,
                'subtotal' => $total,
                'tax_amount' => 0,
                'total_amount' => $total,
                'status' => 'pending',
                'payment_method' => 'razorpay',
                'payment_status' => 'pending',
                'payment_transaction_id' => $razorpayOrder->id,
                'order_items' => $orderItems,
                'notes' => 'Cart checkout',
            ]);

            return response()->json([
                'order_id' => $razorpayOrder->id,
                'amount' => $razorpayOrder->amount,
                'currency' => $razorpayOrder->currency,
                'key_id' => config('razorpay.key_id'),
                'order_reference' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Cart Razorpay checkout failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to start Razorpay checkout right now.'], 500);
        }
    }

    public function verifyRazorpayPayment(Request $request)
    {
        if (! $this->usesRazorpayCheckout()) {
            return response()->json(['success' => false, 'message' => 'Razorpay checkout is available only for India / INR orders.'], 422);
        }

        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
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

        $order = Order::where('user_id', $user->id)
            ->where('payment_method', 'razorpay')
            ->where('payment_transaction_id', $request->string('razorpay_order_id')->toString())
            ->latest()
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Unable to find the pending cart order.'], 404);
        }

        if ($order->status === 'completed') {
            return response()->json([
                'success' => true,
                'redirect' => route('account.library'),
            ]);
        }

        try {
            DB::transaction(function () use ($order, $request, $user) {
                $order->update([
                    'status' => 'completed',
                    'payment_status' => 'completed',
                    'completed_at' => now(),
                    'billing_details' => array_merge($order->billing_details ?? [], [
                        'razorpay_order_id' => $request->string('razorpay_order_id')->toString(),
                        'razorpay_payment_id' => $request->string('razorpay_payment_id')->toString(),
                    ]),
                ]);

                foreach ($this->normaliseOrderItems($order->order_items) as $item) {
                    if (empty($item['product_id'])) {
                        continue;
                    }

                    if (! $user->hasMusicProductAccess((int) $item['product_id'])) {
                        $this->accessControlService->grantMusicProductAccess(
                            $user,
                            (int) $item['product_id'],
                            'single_purchase',
                            null,
                            (string) $order->id
                        );
                    }
                }

                $user->cartItems()->delete();
                session()->forget('cart');
                $this->affiliateService->recordOrderConversion($order, 'INR');
            });

            return response()->json([
                'success' => true,
                'redirect' => route('account.library'),
                'message' => 'Payment successful. Your cart items are now available in your library.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Cart Razorpay verification failed', [
                'user_id' => $user->id,
                'razorpay_order_id' => $request->string('razorpay_order_id')->toString(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Unable to complete your cart payment.'], 500);
        }
    }

    public function checkoutSuccess(Request $request)
    {
        $token = (string) $request->query('token', '');
        $user = $request->user();

        if ($token === '' || ! $user) {
            return redirect()->route('cart')->with('error', 'Payment verification failed.');
        }

        $order = Order::where('user_id', $user->id)
            ->where('payment_method', 'paypal')
            ->where('payment_transaction_id', $token)
            ->latest()
            ->first();

        if (! $order) {
            return redirect()->route('cart')->with('error', 'Unable to find the pending cart order.');
        }

        if ($order->status === 'completed') {
            return redirect()->route('account.library')->with('success', 'Your purchase is already available in your library.');
        }

        try {
            $capture = $this->capturePayPalOrder($token);
            $captureStatus = strtoupper((string) ($capture['status'] ?? ''));

            if ($captureStatus !== 'COMPLETED') {
                throw new \RuntimeException('PayPal order capture did not complete.');
            }

            DB::transaction(function () use ($order, $user) {
                $order->update([
                    'status' => 'completed',
                    'payment_status' => 'completed',
                    'completed_at' => now(),
                ]);

                foreach ($this->normaliseOrderItems($order->order_items) as $item) {
                    if (empty($item['product_id'])) {
                        continue;
                    }

                    if (! $user->hasMusicProductAccess((int) $item['product_id'])) {
                        $this->accessControlService->grantMusicProductAccess(
                            $user,
                            (int) $item['product_id'],
                            'single_purchase',
                            null,
                            (string) $order->id
                        );
                    }
                }

                $user->cartItems()->delete();
                session()->forget('cart');
                $this->affiliateService->recordOrderConversion($order, 'USD');
            });

            return redirect()->route('account.library')->with('success', 'Payment successful. Your cart items are now available in your library.');
        } catch (\Throwable $e) {
            Log::error('Cart PayPal capture failed', [
                'user_id' => $user->id,
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('cart')->with('error', 'Payment verification failed.');
        }
    }

    public function checkoutCancel(Request $request)
    {
        $token = (string) $request->query('token', '');

        if ($token !== '' && $request->user()) {
            Order::where('user_id', $request->user()->id)
                ->where('payment_method', 'paypal')
                ->where('payment_transaction_id', $token)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled',
                ]);
        }

        return redirect()->route('cart')->with('message', 'Checkout was cancelled.');
    }

    private function getCartItems(): Collection
    {
        if (auth()->check()) {
            return auth()->user()->cartItems()->with('product.category')->get()->map(function ($item) {
                return $this->hydrateCartItemPrices($item);
            });
        }

        $sessionCart = session()->get('cart', []);

        return collect($sessionCart)->map(function ($item, $id) {
            $product = Product::with('category')->find($id);
            $pricing = $product ? $this->studentPricing->forRegularProduct($product, auth()->user()) : null;

            return $product ? $this->hydrateCartItemPrices((object) [
                'product' => $product,
                'product_id' => $id,
                'quantity' => $item['quantity'] ?? 1,
                'price' => $pricing['final_usd'] ?? $item['price'] ?? $product->getCurrentPrice(),
            ]) : null;
        })->filter()->values();
    }

    private function createPayPalOrder(array $orderItems, float $total): array
    {
        $response = Http::withBasicAuth(
            config('paypal.client_id'),
            config('paypal.client_secret')
        )->asForm()->post($this->paypalBaseUrl() . '/v1/oauth2/token', [
            'grant_type' => 'client_credentials',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Unable to fetch PayPal access token.');
        }

        $accessToken = $response->json('access_token');

        $purchaseItems = collect($orderItems)->map(function ($item) {
            return [
                'name' => $item['product_name'],
                'quantity' => (string) $item['quantity'],
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format((float) $item['price'], 2, '.', ''),
                ],
            ];
        })->all();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'cart_' . Auth::id(),
                'description' => 'Mental Fitness Store cart purchase',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format($total, 2, '.', ''),
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => 'USD',
                            'value' => number_format($total, 2, '.', ''),
                        ],
                    ],
                ],
                'items' => $purchaseItems,
            ]],
            'application_context' => [
                'return_url' => route('cart.checkout.success'),
                'cancel_url' => route('cart.checkout.cancel'),
                'brand_name' => config('app.name'),
                'user_action' => 'PAY_NOW',
            ],
        ];

        $orderResponse = Http::withToken($accessToken)
            ->withHeaders(['Prefer' => 'return=representation'])
            ->post($this->paypalBaseUrl() . '/v2/checkout/orders', $payload);

        if (! $orderResponse->successful()) {
            throw new \RuntimeException('Unable to create PayPal order.');
        }

        return $orderResponse->json();
    }

    private function capturePayPalOrder(string $paypalOrderId): array
    {
        $response = Http::withBasicAuth(
            config('paypal.client_id'),
            config('paypal.client_secret')
        )->asForm()->post($this->paypalBaseUrl() . '/v1/oauth2/token', [
            'grant_type' => 'client_credentials',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Unable to fetch PayPal access token.');
        }

        $captureResponse = Http::withToken($response->json('access_token'))
            ->withHeaders(['Prefer' => 'return=representation'])
            ->post($this->paypalBaseUrl() . '/v2/checkout/orders/' . $paypalOrderId . '/capture');

        if (! $captureResponse->successful()) {
            throw new \RuntimeException('Unable to capture PayPal order.');
        }

        return $captureResponse->json();
    }

    private function paypalBaseUrl(): string
    {
        return config('paypal.api_url.' . config('paypal.mode', 'sandbox'));
    }

    private function normaliseOrderItems(mixed $orderItems): array
    {
        if (is_array($orderItems)) {
            return $orderItems;
        }

        if (is_string($orderItems) && $orderItems !== '') {
            $decoded = json_decode($orderItems, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function usesRazorpayCheckout(): bool
    {
        return session('user_currency') === 'INR' || session('payment_gateway') === 'razorpay';
    }

    private function checkoutCurrency(): string
    {
        return $this->usesRazorpayCheckout() ? 'INR' : 'USD';
    }

    private function buildOrderItems(Collection $cartItems, string $currency): array
    {
        return $cartItems->map(function ($item) use ($currency) {
            $quantity = max(1, (int) ($item->quantity ?? 1));
            $unitPrice = $this->resolveItemPriceForCurrency($item, $currency);

            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? 'Product',
                'quantity' => $quantity,
                'price' => $unitPrice,
                'total' => round($unitPrice * $quantity, 2),
            ];
        })->values()->all();
    }

    private function resolveItemPriceForCurrency(mixed $item, string $currency): float
    {
        return $currency === 'INR'
            ? (float) ($item->price_inr ?? (($item->price ?? 0) * CurrencyHelper::USD_TO_INR))
            : (float) ($item->price_usd ?? $item->price ?? 0);
    }

    private function hydrateCartItemPrices(mixed $item): mixed
    {
        $product = $item->product ?? null;
        $pricing = $product
            ? $this->studentPricing->forRegularProduct($product, auth()->user())
            : null;

        $item->price_usd = $pricing['final_usd'] ?? (float) ($item->price ?? 0);
        $item->price_inr = $pricing['final_inr'] ?? ($item->price_usd * CurrencyHelper::USD_TO_INR);
        $item->price = $item->price_usd;

        return $item;
    }
}
