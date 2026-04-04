<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use Livewire\WithPagination;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SubscriptionManager extends AdminComponent
{
    use WithPagination;

    protected string $pageTitle = 'User Subscriptions';
    protected string $pageHeader = 'User Subscriptions';

    // Filters
    public string $search      = '';
    public string $statusFilter = '';

    // ── Assign Plan Modal ──────────────────────────────────────
    public bool   $showPlanModal  = false;
    public string $planUserId     = '';
    public string $planSlug       = '';
    public string $planDuration   = '30';   // days
    public string $planPrice      = '';
    public bool   $planIsTrial    = false;
    public string $planUserSearch = '';     // live search inside modal

    // ── Grant Product Modal ────────────────────────────────────
    public bool   $showProductModal   = false;
    public string $productUserId      = '';
    public string $productId          = '';
    public string $productUserSearch  = '';
    public string $productSearch      = '';

    // ── Cancel/Extend modal ────────────────────────────────────
    public bool   $showActionModal  = false;
    public string $actionType       = '';   // 'cancel' | 'extend'
    public ?int   $actionSubId      = null;
    public string $extendDays       = '30';

    protected $queryString = ['search', 'statusFilter'];

    public function updatingSearch():    void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    // ─────────────────────────────────────────────────────────────
    //  ASSIGN PLAN TO USER
    // ─────────────────────────────────────────────────────────────

    public function openPlanModal(): void
    {
        $this->planUserId     = '';
        $this->planSlug       = '';
        $this->planDuration   = '30';
        $this->planPrice      = '';
        $this->planIsTrial    = false;
        $this->planUserSearch = '';
        $this->resetErrorBag();
        $this->showPlanModal = true;
    }

    public function updatedPlanSlug(string $slug): void
    {
        if ($slug) {
            $plan = SubscriptionPlan::where('slug', $slug)->first();
            if ($plan) {
                $this->planPrice = (string) ($plan->inr_price ?? $plan->price ?? '');
                $this->planDuration = match ($plan->billing_cycle) {
                    'monthly'  => '30',
                    'yearly'   => '365',
                    'lifetime' => '36500',
                    default    => '30',
                };
            }
        }
    }

    public function assignPlan(): void
    {
        $this->validate([
            'planUserId'   => 'required|exists:users,id',
            'planSlug'     => 'required|exists:subscription_plans,slug',
            'planDuration' => 'required|integer|min:1',
            'planPrice'    => 'nullable|numeric|min:0',
        ], [
            'planUserId.required' => 'Please select a user.',
            'planSlug.required'   => 'Please select a plan.',
        ]);

        $plan = SubscriptionPlan::where('slug', $this->planSlug)->firstOrFail();
        $user = User::findOrFail($this->planUserId);

        // Deactivate existing active subscriptions for this plan
        Subscription::where('user_id', $user->id)
            ->where('plan_type', $this->planSlug)
            ->where('status', 'active')
            ->update(['status' => 'superseded']);

        Subscription::create([
            'user_id'        => $user->id,
            'plan_type'      => $this->planSlug,
            'price'          => $this->planPrice !== '' ? (float) $this->planPrice : ($plan->inr_price ?? $plan->price ?? 0),
            'status'         => 'active',
            'starts_at'      => now(),
            'ends_at'        => now()->addDays((int) $this->planDuration),
            'payment_method' => 'admin_manual',
            'is_trial'       => $this->planIsTrial,
            'auto_renew'     => false,
        ]);

        $this->showPlanModal = false;
        session()->flash('success', "Subscribed {$user->name} to \"{$plan->name}\" for {$this->planDuration} days.");
    }

    // ─────────────────────────────────────────────────────────────
    //  GRANT PRODUCT ACCESS
    // ─────────────────────────────────────────────────────────────

    public function openProductModal(): void
    {
        $this->productUserId     = '';
        $this->productId         = '';
        $this->productUserSearch = '';
        $this->productSearch     = '';
        $this->resetErrorBag();
        $this->showProductModal = true;
    }

    public function grantProduct(): void
    {
        $this->validate([
            'productUserId' => 'required|exists:users,id',
            'productId'     => 'required|exists:products,id',
        ], [
            'productUserId.required' => 'Please select a user.',
            'productId.required'     => 'Please select a product.',
        ]);

        $user    = User::findOrFail($this->productUserId);
        $product = Product::findOrFail($this->productId);

        // Check if already granted
        if ($user->hasPurchased($product->id)) {
            $this->addError('productId', "{$user->name} already has access to this product.");
            return;
        }

        // Create a completed order representing admin-granted access
        $orderNumber = 'ADM-' . date('Y') . '-' . strtoupper(Str::random(6));

        Order::create([
            'order_number'           => $orderNumber,
            'user_id'                => $user->id,
            'subtotal'               => 0,
            'tax_amount'             => 0,
            'total_amount'           => 0,
            'status'                 => 'completed',
            'payment_method'         => 'admin_manual',
            'payment_status'         => 'paid',
            'payment_transaction_id' => 'ADMIN-GRANT-' . now()->format('YmdHis'),
            'billing_details'        => ['note' => 'Admin-granted access'],
            'order_items'            => [[
                'product_id' => $product->id,
                'name'       => $product->name,
                'price'      => 0,
                'quantity'   => 1,
            ]],
            'notes'        => 'Access granted manually by admin.',
            'completed_at' => now(),
        ]);

        $this->showProductModal = false;
        session()->flash('success', "Product access to \"{$product->name}\" granted to {$user->name}.");
    }

    // ─────────────────────────────────────────────────────────────
    //  CANCEL / EXTEND SUBSCRIPTION
    // ─────────────────────────────────────────────────────────────

    public function openCancelConfirm(int $id): void
    {
        $this->actionSubId   = $id;
        $this->actionType    = 'cancel';
        $this->showActionModal = true;
    }

    public function openExtend(int $id): void
    {
        $this->actionSubId   = $id;
        $this->actionType    = 'extend';
        $this->extendDays    = '30';
        $this->showActionModal = true;
    }

    public function performAction(): void
    {
        $sub = Subscription::findOrFail($this->actionSubId);

        if ($this->actionType === 'cancel') {
            $sub->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            session()->flash('success', 'Subscription cancelled.');
        } elseif ($this->actionType === 'extend') {
            $this->validate(['extendDays' => 'required|integer|min:1|max:3650']);
            $base = $sub->ends_at && $sub->ends_at->isFuture() ? $sub->ends_at : now();
            $sub->update([
                'ends_at' => $base->addDays((int) $this->extendDays),
                'status'  => 'active',
            ]);
            session()->flash('success', "Subscription extended by {$this->extendDays} days.");
        }

        $this->showActionModal = false;
        $this->actionSubId = null;
    }

    public function closeActionModal(): void
    {
        $this->showActionModal = false;
        $this->actionSubId = null;
    }

    // ─────────────────────────────────────────────────────────────
    //  VIEW DATA
    // ─────────────────────────────────────────────────────────────

    protected function getViewData(): array
    {
        $subscriptions = Subscription::with('user')
            ->when($this->search, function ($q) {
                $q->whereHas('user', fn($u) =>
                    $u->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                );
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(15);

        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        // Users for plan modal dropdown (search-filtered)
        $modalUsers    = User::when($this->planUserSearch, fn($q) =>
                $q->where('name', 'like', "%{$this->planUserSearch}%")
                  ->orWhere('email', 'like', "%{$this->planUserSearch}%")
            )->orderBy('name')->limit(20)->get();

        // Users for product modal dropdown
        $productModalUsers = User::when($this->productUserSearch, fn($q) =>
                $q->where('name', 'like', "%{$this->productUserSearch}%")
                  ->orWhere('email', 'like', "%{$this->productUserSearch}%")
            )->orderBy('name')->limit(20)->get();

        // Products for product modal
        $products = Product::when($this->productSearch, fn($q) =>
                $q->where('name', 'like', "%{$this->productSearch}%")
            )->where('is_active', true)->orderBy('name')->limit(30)->get();

        return [
            'subscriptions'     => $subscriptions,
            'plans'             => $plans,
            'modalUsers'        => $modalUsers,
            'productModalUsers' => $productModalUsers,
            'products'          => $products,
            'stats' => [
                'total'      => Subscription::count(),
                'active'     => Subscription::where('status', 'active')->where('ends_at', '>', now())->count(),
                'expired'    => Subscription::where('ends_at', '<', now())->count(),
                'manual'     => Subscription::where('payment_method', 'admin_manual')->count(),
            ],
        ];
    }
}
