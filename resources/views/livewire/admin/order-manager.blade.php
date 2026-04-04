<div>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row mb-4">
        <div class="col-6 col-lg-3">
            <div class="info-box bg-info">
                <span class="info-box-icon"><i class="fas fa-list-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Orders</span>
                    <span class="info-box-number">{{ $stats['total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-warning">
                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Pending</span>
                    <span class="info-box-number">{{ $stats['pending'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-success">
                <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Completed</span>
                    <span class="info-box-number">{{ $stats['completed'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-primary">
                <span class="info-box-icon"><i class="fas fa-rupee-sign"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Revenue</span>
                    <span class="info-box-number">₹{{ number_format($stats['revenue'], 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card card-outline card-primary mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-5">
                    <input type="text" wire:model.debounce.400ms="search" class="form-control"
                        placeholder="Search order number, customer name or email…">
                </div>
                <div class="col-md-3">
                    <select wire:model="statusFilter" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model="paymentFilter" class="form-select">
                        <option value="">All Payment States</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button wire:click="$set('search','')" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Orders table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td><code>{{ $order->order_number }}</code></td>
                                <td>
                                    @if($order->user)
                                        <strong>{{ $order->user->name }}</strong><br>
                                        <small class="text-muted">{{ $order->user->email }}</small>
                                    @else
                                        <span class="text-muted">Guest</span>
                                    @endif
                                </td>
                                <td>₹{{ number_format($order->total_amount, 2) }}</td>
                                <td>
                                    @php
                                        $pc = match($order->payment_status) {
                                            'paid'    => 'success',
                                            'pending' => 'warning',
                                            'failed'  => 'danger',
                                            default   => 'secondary'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $pc }}">{{ ucfirst($order->payment_status) }}</span>
                                    @if($order->payment_method)
                                        <br><small class="text-muted">{{ $order->payment_method }}</small>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $sc = match($order->status) {
                                            'completed' => 'success',
                                            'pending'   => 'warning',
                                            'failed'    => 'danger',
                                            'refunded'  => 'info',
                                            default     => 'secondary'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $sc }}">{{ ucfirst($order->status) }}</span>
                                </td>
                                <td>
                                    <small>{{ $order->created_at->format('d M Y') }}<br>{{ $order->created_at->format('H:i') }}</small>
                                </td>
                                <td>
                                    <button wire:click="viewOrder({{ $order->id }})" class="btn btn-xs btn-info me-1" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    @if($order->status === 'pending')
                                        <button wire:click="updateStatus({{ $order->id }}, 'completed')" class="btn btn-xs btn-success" title="Mark completed"
                                            onclick="return confirm('Mark this order as completed?')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No orders found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
            <div class="card-footer">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    {{-- Order Detail Modal --}}
    @if($showModal && $selectedOrder)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2"></i>Order: {{ $selectedOrder->order_number }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Customer</h6>
                            <p class="mb-1">{{ $selectedOrder->user?->name ?? 'Guest' }}</p>
                            <p class="mb-1 text-muted">{{ $selectedOrder->user?->email }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">Payment</h6>
                            <p class="mb-1">Method: {{ $selectedOrder->payment_method ?? 'N/A' }}</p>
                            <p class="mb-1">Transaction: <code>{{ $selectedOrder->payment_transaction_id ?? '-' }}</code></p>
                            <p class="mb-1">Status: <span class="badge bg-{{ $selectedOrder->payment_status === 'paid' ? 'success' : 'warning' }}">{{ $selectedOrder->payment_status }}</span></p>
                        </div>
                    </div>

                    @if($selectedOrder->order_items)
                        <h6 class="fw-bold">Items</h6>
                        <table class="table table-sm table-bordered mb-3">
                            <thead class="table-light">
                                <tr><th>Product</th><th>Qty</th><th>Price</th></tr>
                            </thead>
                            <tbody>
                                @foreach((array)$selectedOrder->order_items as $item)
                                    <tr>
                                        <td>{{ $item['name'] ?? 'Unknown' }}</td>
                                        <td>{{ $item['quantity'] ?? 1 }}</td>
                                        <td>₹{{ number_format($item['price'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <table class="table table-sm">
                                <tr><td>Subtotal</td><td class="text-end">₹{{ number_format($selectedOrder->subtotal, 2) }}</td></tr>
                                <tr><td>Tax</td><td class="text-end">₹{{ number_format($selectedOrder->tax_amount, 2) }}</td></tr>
                                <tr class="fw-bold"><td>Total</td><td class="text-end">₹{{ number_format($selectedOrder->total_amount, 2) }}</td></tr>
                            </table>
                        </div>
                    </div>

                    @if($selectedOrder->notes)
                        <div class="alert alert-light"><strong>Notes:</strong> {{ $selectedOrder->notes }}</div>
                    @endif
                </div>
                <div class="modal-footer">
                    <span class="me-auto text-muted small">Created {{ $selectedOrder->created_at->format('d M Y H:i') }}</span>
                    <button class="btn btn-secondary" wire:click="closeModal">Close</button>
                    @if($selectedOrder->status === 'pending')
                        <button class="btn btn-success" wire:click="updateStatus({{ $selectedOrder->id }}, 'completed')" onclick="return confirm('Mark as completed?')">
                            <i class="fas fa-check me-1"></i>Mark Completed
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
