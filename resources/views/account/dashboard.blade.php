@extends('account.layout')
@section('title', 'Dashboard')

@section('account-content')
<div>
    <h2 class="fw-bold mb-1">Welcome back, {{ $user->name }}! 👋</h2>
    <p class="text-muted mb-4">Here's an overview of your account.</p>

    {{-- Stats row --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary mb-1">{{ $orderCount }}</div>
                    <div class="small text-muted">Total Orders</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="display-6 fw-bold text-success mb-1">
                        @if($activeSubscription)
                            <i class="fas fa-check-circle"></i>
                        @else
                            <i class="fas fa-times-circle text-secondary"></i>
                        @endif
                    </div>
                    <div class="small text-muted">
                        @if($activeSubscription)
                            {{ ucfirst($activeSubscription->plan_type) }} Plan
                        @else
                            No Active Subscription
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="display-6 fw-bold text-info mb-1">
                        {{ $user->created_at->format('Y') }}
                    </div>
                    <div class="small text-muted">Member Since</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Active subscription banner --}}
    @if($activeSubscription)
    <div class="alert alert-success d-flex align-items-center gap-3 mb-4">
        <i class="fas fa-crown fa-2x text-warning"></i>
        <div>
            <div class="fw-bold">Active Subscription: {{ ucfirst($activeSubscription->plan_type) }}</div>
            <div class="small">
                Expires: {{ $activeSubscription->ends_at ? $activeSubscription->ends_at->format('d M Y') : 'Never' }}
            </div>
        </div>
        <a href="{{ route('account.library') }}" class="btn btn-success ms-auto">Access Library</a>
    </div>
    @else
    <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
        <i class="fas fa-headphones fa-2x"></i>
        <div>
            <div class="fw-bold">Upgrade for unlimited access</div>
            <div class="small">Subscribe to unlock all premium mental wellness audio content.</div>
        </div>
        <a href="{{ route('subscription') }}" class="btn btn-primary ms-auto">View Plans</a>
    </div>
    @endif

    {{-- Recent orders --}}
    @if($recentOrders->count())
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-receipt me-2 text-muted"></i>Recent Orders</span>
            <a href="{{ route('account.orders') }}" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $order)
                    <tr>
                        <td><code>{{ $order->order_number }}</code></td>
                        <td>₹{{ number_format($order->total_amount, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ $order->status === 'completed' ? 'success' : ($order->status === 'pending' ? 'warning' : 'secondary') }}">
                                {{ ucfirst($order->status) }}
                            </span>
                        </td>
                        <td class="text-muted small">{{ $order->created_at->format('d M Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-shopping-bag fa-3x mb-3 d-block opacity-25"></i>
            <p class="mb-3">You haven't placed any orders yet.</p>
            <a href="{{ route('products') }}" class="btn btn-primary">Browse Products</a>
        </div>
    </div>
    @endif
</div>
@endsection
