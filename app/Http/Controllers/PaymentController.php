<?php

namespace App\Http\Controllers;

use App\Helpers\CurrencyHelper;
use App\Models\TtsAudioProduct;
use App\Models\ProductVersion;
use App\Models\TtsProductPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    // ─────────────────────────────────────────────
    //  Razorpay – create order (India)
    // ─────────────────────────────────────────────

    public function razorpayCreateOrder(Request $request)
    {
        $request->validate([
            'product_id'   => 'required|integer',
            'version_id'   => 'nullable|integer',
            'product_type' => 'nullable|string|in:audio,ebook_pdf,ebook_bundle',
        ]);

        $product     = TtsAudioProduct::findOrFail($request->product_id);
        $productType = $request->product_type ?? $product->product_type ?? 'audio';

        $amountInr = $this->resolveInrAmount($product, $productType, $request->version_id);

        try {
            $api = new \Razorpay\Api\Api(
                config('razorpay.key_id'),
                config('razorpay.key_secret')
            );

            $order = $api->order->create([
                'amount'          => (int) ($amountInr * 100), // paise
                'currency'        => 'INR',
                'receipt'         => 'mf_' . Str::random(10),
                'payment_capture' => 1,
                'notes'           => [
                    'product_id'   => $product->id,
                    'version_id'   => $request->version_id,
                    'product_type' => $productType,
                    'user_id'      => Auth::id(),
                ],
            ]);

            return response()->json([
                'order_id'   => $order->id,
                'amount'     => $order->amount,
                'currency'   => $order->currency,
                'key_id'     => config('razorpay.key_id'),
                'product_name' => $product->name,
            ]);
        } catch (\Throwable $e) {
            Log::error('Razorpay order creation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Payment initialisation failed.'], 500);
        }
    }

    public function razorpayVerify(Request $request)
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
            'product_id'          => 'required|integer',
            'version_id'          => 'nullable|integer',
            'product_type'        => 'nullable|string',
        ]);

        $expectedSignature = hash_hmac(
            'sha256',
            $request->razorpay_order_id . '|' . $request->razorpay_payment_id,
            config('razorpay.key_secret')
        );

        if (! hash_equals($expectedSignature, $request->razorpay_signature)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 422);
        }

        $this->recordPurchase(
            $request->product_id,
            $request->version_id,
            $request->product_type ?? 'audio',
            'razorpay',
            $request->razorpay_payment_id
        );

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────
    //  PayPal – create order (outside India)
    // ─────────────────────────────────────────────

    public function paypalCreateOrder(Request $request)
    {
        $request->validate([
            'product_id'   => 'required|integer',
            'version_id'   => 'nullable|integer',
            'product_type' => 'nullable|string|in:audio,ebook_pdf,ebook_bundle',
        ]);

        $product     = TtsAudioProduct::findOrFail($request->product_id);
        $productType = $request->product_type ?? $product->product_type ?? 'audio';
        $amountUsd   = $this->resolveUsdAmount($product, $productType, $request->version_id);

        try {
            $token = $this->getPayPalAccessToken();

            $mode    = config('paypal.mode', 'sandbox');
            $baseUrl = config('paypal.api_url.' . $mode);

            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->post($baseUrl . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => 'mf_' . $product->id,
                        'description'  => $product->name,
                        'amount'       => [
                            'currency_code' => 'USD',
                            'value'         => number_format($amountUsd, 2, '.', ''),
                        ],
                    ]],
                    'application_context' => [
                        'return_url' => config('paypal.return_url'),
                        'cancel_url' => config('paypal.cancel_url'),
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->body());
            }

            $data = $response->json();

            // Store pending info in session for callback
            session([
                'paypal_product_id'   => $product->id,
                'paypal_version_id'   => $request->version_id,
                'paypal_product_type' => $productType,
            ]);

            return response()->json([
                'order_id'    => $data['id'],
                'approve_url' => collect($data['links'])->firstWhere('rel', 'approve')['href'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('PayPal order creation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Payment initialisation failed.'], 500);
        }
    }

    public function paypalSuccess(Request $request)
    {
        $token   = $request->query('token');
        $payerId = $request->query('PayerID');

        if (! $token) {
            return redirect('/')->with('error', 'Payment cancelled.');
        }

        try {
            $accessToken = $this->getPayPalAccessToken();
            $mode        = config('paypal.mode', 'sandbox');
            $baseUrl     = config('paypal.api_url.' . $mode);

            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->post($baseUrl . '/v2/checkout/orders/' . $token . '/capture');

            if ($response->successful()) {
                $this->recordPurchase(
                    session('paypal_product_id'),
                    session('paypal_version_id'),
                    session('paypal_product_type', 'audio'),
                    'paypal',
                    $token
                );
                return redirect('/')->with('success', 'Payment successful! Your purchase is now available.');
            }
        } catch (\Throwable $e) {
            Log::error('PayPal capture failed', ['error' => $e->getMessage()]);
        }

        return redirect('/')->with('error', 'Payment verification failed.');
    }

    public function paypalCancel()
    {
        return redirect('/')->with('info', 'Payment was cancelled.');
    }

    // ─────────────────────────────────────────────
    //  Razorpay webhook (server-side verification)
    // ─────────────────────────────────────────────

    public function razorpayWebhook(Request $request)
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        $expected  = hash_hmac('sha256', $payload, config('razorpay.webhook_secret'));

        if (! hash_equals($expected, $signature ?? '')) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $event = $request->json('event');
        if ($event === 'payment.captured') {
            $notes = $request->json('payload.payment.entity.notes', []);
            if (! empty($notes['product_id'])) {
                $this->recordPurchase(
                    $notes['product_id'],
                    $notes['version_id'] ?? null,
                    $notes['product_type'] ?? 'audio',
                    'razorpay',
                    $request->json('payload.payment.entity.id')
                );
            }
        }

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────

    private function resolveInrAmount(TtsAudioProduct $product, string $type, ?int $versionId): float
    {
        if ($versionId) {
            $v = ProductVersion::find($versionId);
            if ($v && $v->inr_price) {
                return (float) $v->inr_price;
            }
        }
        return match ($type) {
            'ebook_pdf'    => (float) ($product->pdf_price_inr   ?? ($product->pdf_price    * CurrencyHelper::USD_TO_INR)),
            'ebook_bundle' => (float) ($product->bundle_price_inr ?? ($product->bundle_price * CurrencyHelper::USD_TO_INR)),
            default        => (float) ($product->inr_sale_price  ?? $product->inr_price ?? ($product->price * CurrencyHelper::USD_TO_INR)),
        };
    }

    private function resolveUsdAmount(TtsAudioProduct $product, string $type, ?int $versionId): float
    {
        if ($versionId) {
            $v = ProductVersion::find($versionId);
            if ($v && $v->price) {
                return (float) $v->price;
            }
        }
        return match ($type) {
            'ebook_pdf'    => (float) ($product->pdf_price    ?? 4.90),
            'ebook_bundle' => (float) ($product->bundle_price  ?? 10.00),
            default        => (float) ($product->sale_price   ?? $product->price),
        };
    }

    private function recordPurchase(int $productId, ?int $versionId, string $productType, string $gateway, string $txnId): void
    {
        if (! Auth::check()) {
            return;
        }

        TtsProductPurchase::firstOrCreate(
            ['user_id' => Auth::id(), 'tts_audio_product_id' => $productId, 'version_id' => $versionId],
            [
                'status'         => 'completed',
                'payment_method' => $gateway,
                'transaction_id' => $txnId,
                'product_type'   => $productType,
            ]
        );
    }

    private function getPayPalAccessToken(): string
    {
        $mode = config('paypal.mode', 'sandbox');
        $base = config('paypal.api_url.' . $mode);

        $response = \Illuminate\Support\Facades\Http::withBasicAuth(
            config('paypal.client_id'),
            config('paypal.client_secret')
        )->asForm()->post($base . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $response->json('access_token');
    }
}
