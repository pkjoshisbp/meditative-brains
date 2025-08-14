<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\Order;
use App\Services\PayPalService;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $paypalService;
    protected $accessControlService;

    public function __construct(PayPalService $paypalService, AccessControlService $accessControlService)
    {
        $this->paypalService = $paypalService;
        $this->accessControlService = $accessControlService;
    }

    /**
     * Create payment for single product purchase
     */
    public function createProductPayment(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $product = Product::findOrFail($request->product_id);

        // Check if user already owns this product
        if ($user->hasMusicProductAccess($product->id)) {
            return response()->json([
                'error' => 'You already have access to this product',
                'has_access' => true
            ], 400);
        }

        $result = $this->paypalService->createProductOrder($user, $product);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'paypal_order_id' => $result['paypal_order_id'],
                'approval_url' => $result['approval_url'],
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->sale_price ?? $product->price
                ]
            ]);
        }

        return response()->json([
            'error' => $result['error']
        ], 500);
    }

    /**
     * Create payment for subscription
     */
    public function createSubscriptionPayment(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id'
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Check if user already has an active subscription
        if ($user->hasActiveSubscription()) {
            return response()->json([
                'error' => 'You already have an active subscription',
                'current_subscription' => $user->getActiveSubscription()
            ], 400);
        }

        $result = $this->paypalService->createSubscriptionOrder($user, $plan);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'paypal_order_id' => $result['paypal_order_id'],
                'approval_url' => $result['approval_url'],
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'billing_cycle' => $plan->billing_cycle
                ]
            ]);
        }

        return response()->json([
            'error' => $result['error']
        ], 500);
    }

    /**
     * Handle successful payment callback
     */
    public function handleSuccess(Request $request)
    {
        $request->validate([
            'paypal_order_id' => 'required|string',
            'type' => 'required|in:product,subscription'
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        DB::beginTransaction();
        
        try {
            if ($request->type === 'product') {
                $result = $this->handleProductSuccess($request, $user);
            } else {
                $result = $this->handleSubscriptionSuccess($request, $user);
            }

            if ($result['success']) {
                DB::commit();
                return response()->json($result);
            } else {
                DB::rollBack();
                return response()->json($result, 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'paypal_order_id' => $request->paypal_order_id
            ]);

            return response()->json([
                'error' => 'Payment processing failed'
            ], 500);
        }
    }

    /**
     * Handle successful product purchase
     */
    private function handleProductSuccess(Request $request, $user)
    {
        $productId = $request->get('product_id');
        $product = Product::findOrFail($productId);

        return $this->paypalService->processProductPurchase(
            $request->paypal_order_id,
            $user,
            $product
        );
    }

    /**
     * Handle successful subscription purchase
     */
    private function handleSubscriptionSuccess(Request $request, $user)
    {
        $planId = $request->get('plan_id');
        $plan = SubscriptionPlan::findOrFail($planId);

        return $this->paypalService->processSubscriptionPurchase(
            $request->paypal_order_id,
            $user,
            $plan
        );
    }

    /**
     * Get user's purchase history
     */
    public function purchaseHistory(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $orders = $user->orders()
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->with('user')
            ->get();

        $subscriptions = $user->subscriptions()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'payment_method' => $order->payment_method,
                    'completed_at' => $order->completed_at,
                    'items' => $order->order_items
                ];
            }),
            'subscriptions' => $subscriptions->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'plan_type' => $subscription->plan_type,
                    'price' => $subscription->price,
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                    'auto_renew' => $subscription->auto_renew,
                    'is_active' => $subscription->isActive()
                ];
            })
        ]);
    }

    /**
     * Cancel user's subscription
     */
    public function cancelSubscription(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $subscription = $user->getActiveSubscription();
        
        if (!$subscription) {
            return response()->json([
                'error' => 'No active subscription found'
            ], 404);
        }

        $result = $this->paypalService->cancelSubscription($subscription);

        return response()->json($result);
    }

    /**
     * Get available subscription plans
     */
    public function getSubscriptionPlans(Request $request)
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'plans' => $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'billing_cycle' => $plan->billing_cycle,
                    'features' => $plan->features,
                    'includes_music_library' => $plan->includes_music_library,
                    'includes_all_tts_categories' => $plan->includes_all_tts_categories,
                    'included_tts_categories' => $plan->included_tts_categories,
                    'trial_days' => $plan->trial_days,
                    'is_featured' => $plan->is_featured
                ];
            })
        ]);
    }

    /**
     * PayPal webhook handler
     */
    public function webhook(Request $request)
    {
        Log::info('PayPal webhook received', $request->all());

        // Verify webhook signature in production
        if (!$this->paypalService->verifyWebhook($request->headers->all(), $request->getContent())) {
            return response()->json(['error' => 'Invalid webhook signature'], 400);
        }

        $eventType = $request->input('event_type');
        $resource = $request->input('resource');

        $result = $this->paypalService->handleWebhook($eventType, $resource);

        return response()->json($result);
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $request->validate([
            'paypal_order_id' => 'required|string'
        ]);

        $order = Order::where('payment_transaction_id', $request->paypal_order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'error' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total_amount' => $order->total_amount,
            'completed_at' => $order->completed_at
        ]);
    }
}
