@extends('account.layout')
@section('title', 'My Orders')

@section('account-content')
<div>
    <h2 class="fw-bold mb-1">My Orders</h2>
    <p class="text-muted mb-4">Your complete order history.</p>

    @if($orders->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-receipt fa-3x mb-3 d-block opacity-25"></i>
            <h5 class="fw-semibold">No orders yet</h5>
            <p class="mb-3">Once you purchase something, your orders will appear here.</p>
            <a href="{{ route('products') }}" class="btn btn-primary">Browse Products</a>
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                        <tr>
                            <td><code class="fw-semibold">{{ $order->order_number }}</code></td>
                            <td>
                                @if($order->order_items)
                                    @foreach($order->order_items as $item)
                                        <div class="small">{{ $item['name'] ?? 'Product' }}
                                            @if(isset($item['quantity']) && $item['quantity'] > 1)
                                                <span class="badge bg-secondary">×{{ $item['quantity'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="fw-semibold">₹{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }}">
                                    {{ ucfirst($order->payment_status ?? 'pending') }}
                                </span>
                            </td>
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
        @if($orders->hasPages())
        <div class="card-footer bg-white">
            {{ $orders->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
