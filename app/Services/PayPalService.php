<?php

namespace App\Services;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Product;
use App\Models\Order;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PayPalService
{
    private $accessControlService;
    private $baseUrl;
    private $clientId;
    private $clientSecret;

    public function __construct(AccessControlService $accessControlService)
    {
        $this->accessControlService = $accessControlService;
        $this->clientId = config('paypal.client_id');
        $this->clientSecret = config('paypal.client_secret');
        $this->baseUrl = config('paypal.mode') === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken()
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        throw new \Exception('Failed to get PayPal access token');
    }

    /**
     * Create order for single product purchase
     */
    public function createProductOrder(User $user, Product $product, array $options = [])
    {
        $price = $product->sale_price ?? $product->price;
        
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'product_' . $product->id,
                    'description' => $product->name,
                    'amount' => [
                        'currency_code' => config('paypal.currency'),
                        'value' => number_format($price, 2, '.', '')
                    ],
                    'custom_id' => 'user_' . $user->id . '_product_' . $product->id
                ]
            ],
            'application_context' => [
                'return_url' => config('paypal.return_url') . '?type=product&product_id=' . $product->id,
                'cancel_url' => config('paypal.cancel_url'),
                'brand_name' => config('app.name'),
                'user_action' => 'PAY_NOW'
            ]
        ];

        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withToken($accessToken)
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($this->baseUrl . '/v2/checkout/orders', $orderData);

            if ($response->successful()) {
                $result = $response->json();
                
                // Create pending order in database
                $order = Order::create([
                    'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                    'user_id' => $user->id,
                    'subtotal' => $price,
                    'total_amount' => $price,
                    'status' => 'pending',
                    'payment_method' => 'paypal',
                    'payment_status' => 'pending',
                    'payment_transaction_id' => $result['id'],
                    'order_items' => [
                        [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => 1,
                            'price' => $price,
                            'total' => $price
                        ]
                    ]
                ]);

                return [
                    'success' => true,
                    'paypal_order_id' => $result['id'],
                    'approval_url' => $this->getApprovalUrl($result['links']),
                    'order' => $order
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to create PayPal order'
            ];

        } catch (\Exception $ex) {
            Log::error('PayPal Product Order Creation Failed', [
                'error' => $ex->getMessage(),
                'user_id' => $user->id,
                'product_id' => $product->id
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create PayPal order'
            ];
        }
    }

    /**
     * Create subscription order
     */
    public function createSubscriptionOrder(User $user, SubscriptionPlan $plan)
    {
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'subscription_' . $plan->id,
                    'description' => $plan->name . ' - ' . ucfirst($plan->billing_cycle) . ' Subscription',
                    'amount' => [
                        'currency_code' => config('paypal.currency'),
                        'value' => number_format($plan->price, 2, '.', '')
                    ],
                    'custom_id' => 'user_' . $user->id . '_plan_' . $plan->id
                ]
            ],
            'application_context' => [
                'return_url' => config('paypal.return_url') . '?type=subscription&plan_id=' . $plan->id,
                'cancel_url' => config('paypal.cancel_url'),
                'brand_name' => config('app.name'),
                'user_action' => 'PAY_NOW'
            ]
        ];

        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withToken($accessToken)
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($this->baseUrl . '/v2/checkout/orders', $orderData);

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success' => true,
                    'paypal_order_id' => $result['id'],
                    'approval_url' => $this->getApprovalUrl($result['links']),
                    'plan' => $plan
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to create PayPal subscription order'
            ];

        } catch (\Exception $ex) {
            Log::error('PayPal Subscription Order Creation Failed', [
                'error' => $ex->getMessage(),
                'user_id' => $user->id,
                'plan_id' => $plan->id
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create PayPal subscription order'
            ];
        }
    }

    /**
     * Capture order after user approval
     */
    public function captureOrder($paypalOrderId)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withToken($accessToken)
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($this->baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture');

            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['status'] === 'COMPLETED') {
                    return [
                        'success' => true,
                        'capture_id' => $result['purchase_units'][0]['payments']['captures'][0]['id'],
                        'amount' => $result['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                        'currency' => $result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'],
                        'payer_email' => $result['payer']['email_address'] ?? null
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Order capture failed'
            ];

        } catch (\Exception $ex) {
            Log::error('PayPal Order Capture Failed', [
                'error' => $ex->getMessage(),
                'paypal_order_id' => $paypalOrderId
            ]);

            return [
                'success' => false,
                'error' => 'Failed to capture PayPal order'
            ];
        }
    }

    /**
     * Process successful product purchase
     */
    public function processProductPurchase($paypalOrderId, User $user, Product $product)
    {
        $captureResult = $this->captureOrder($paypalOrderId);
        
        if (!$captureResult['success']) {
            return $captureResult;
        }

        // Update order status
        $order = Order::where('payment_transaction_id', $paypalOrderId)->first();
        if ($order) {
            $order->update([
                'status' => 'completed',
                'payment_status' => 'completed',
                'completed_at' => now()
            ]);
        }

        // Grant access based on product type
        if (in_array($product->audio_type, ['sleep_aid', 'meditation', 'binaural_beats', 'nature_sounds', 'solfeggio'])) {
            // Music product - grant individual product access
            $this->accessControlService->grantMusicProductAccess(
                $user,
                $product->id,
                'single_purchase',
                null, // Lifetime access
                $order->id ?? $paypalOrderId
            );
        } elseif ($product->audio_type === 'tts_affirmation') {
            // TTS product - would need category information
            // For now, grant as individual purchase
        }

        return [
            'success' => true,
            'message' => 'Purchase completed successfully',
            'order' => $order,
            'access_granted' => true
        ];
    }

    /**
     * Process successful subscription purchase
     */
    public function processSubscriptionPurchase($paypalOrderId, User $user, SubscriptionPlan $plan)
    {
        $captureResult = $this->captureOrder($paypalOrderId);
        
        if (!$captureResult['success']) {
            return $captureResult;
        }

        // Calculate subscription dates
        $startsAt = now();
        $endsAt = $plan->billing_cycle === 'yearly' 
            ? $startsAt->copy()->addYear()
            : $startsAt->copy()->addMonth();

        // Create subscription record
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_type' => $plan->slug,
            'price' => $plan->price,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'payment_method' => 'paypal',
            'stripe_subscription_id' => $paypalOrderId, // Using this field for PayPal ID
            'auto_renew' => false // Manual renewal for now
        ]);

        // Grant access based on plan features
        $this->accessControlService->grantSubscriptionAccess(
            $user,
            $plan,
            $endsAt,
            $subscription->id
        );

        return [
            'success' => true,
            'message' => 'Subscription activated successfully',
            'subscription' => $subscription,
            'access_granted' => true,
            'expires_at' => $endsAt
        ];
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription)
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'auto_renew' => false
        ]);

        // Revoke access
        $this->accessControlService->revokeSubscriptionAccess(
            $subscription->user,
            $subscription->id
        );

        return [
            'success' => true,
            'message' => 'Subscription cancelled successfully'
        ];
    }

    /**
     * Get approval URL from PayPal links
     */
    private function getApprovalUrl($links)
    {
        foreach ($links as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        return null;
    }

    /**
     * Verify webhook signature (for production)
     */
    public function verifyWebhook($headers, $body)
    {
        // Implement webhook verification for production
        // For now, return true for development
        return true;
    }

    /**
     * Handle webhook events
     */
    public function handleWebhook($eventType, $resource)
    {
        Log::info('PayPal Webhook Received', [
            'event_type' => $eventType,
            'resource_id' => $resource['id'] ?? null
        ]);

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handlePaymentCaptured($resource);
            
            case 'BILLING.SUBSCRIPTION.CANCELLED':
                return $this->handleSubscriptionCancelled($resource);
            
            case 'BILLING.SUBSCRIPTION.EXPIRED':
                return $this->handleSubscriptionExpired($resource);
            
            default:
                Log::info('Unhandled PayPal webhook event', ['event_type' => $eventType]);
                return ['success' => true, 'message' => 'Event not handled'];
        }
    }

    /**
     * Handle payment captured webhook
     */
    private function handlePaymentCaptured($resource)
    {
        // Additional processing for captured payments
        return ['success' => true];
    }

    /**
     * Handle subscription cancelled webhook
     */
    private function handleSubscriptionCancelled($resource)
    {
        // Handle subscription cancellation
        return ['success' => true];
    }

    /**
     * Handle subscription expired webhook
     */
    private function handleSubscriptionExpired($resource)
    {
        // Handle subscription expiration
        return ['success' => true];
    }
}
