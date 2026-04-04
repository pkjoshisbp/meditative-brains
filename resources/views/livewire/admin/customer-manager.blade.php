<div>
    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row mb-4">
        <div class="col-6 col-lg-4">
            <div class="info-box bg-info">
                <span class="info-box-icon"><i class="fas fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Customers</span>
                    <span class="info-box-number">{{ $stats['total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="info-box bg-success">
                <span class="info-box-icon"><i class="fas fa-user-plus"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">New This Month</span>
                    <span class="info-box-number">{{ $stats['new_month'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="info-box bg-primary">
                <span class="info-box-icon"><i class="fas fa-shopping-bag"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Have Ordered</span>
                    <span class="info-box-number">{{ $stats['with_orders'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Search --}}
    <div class="card card-outline card-primary mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-10">
                    <input type="text" wire:model.debounce.400ms="search" class="form-control"
                        placeholder="Search by name or email…">
                </div>
                <div class="col-md-2">
                    <button wire:click="$set('search','')" class="btn btn-secondary w-100">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Customers table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            <tr>
                                <td>{{ $customer->id }}</td>
                                <td><strong>{{ $customer->name }}</strong></td>
                                <td>{{ $customer->email }}</td>
                                <td>
                                    <span class="badge bg-{{ $customer->orders_count > 0 ? 'success' : 'secondary' }}">
                                        {{ $customer->orders_count }}
                                    </span>
                                </td>
                                <td>
                                    <small>{{ $customer->created_at->format('d M Y') }}</small>
                                </td>
                                <td>
                                    <button wire:click="viewCustomer({{ $customer->id }})" class="btn btn-xs btn-info" title="View details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button wire:click="openEditPassword({{ $customer->id }})" class="btn btn-xs btn-warning" title="Edit password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button wire:click="deleteCustomer({{ $customer->id }})"
                                        onclick="return confirm('Delete this customer? This cannot be undone.')"
                                        class="btn btn-xs btn-danger" title="Delete customer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                    No customers found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($customers->hasPages())
            <div class="card-footer">
                {{ $customers->links() }}
            </div>
        @endif
    </div>

    {{-- Customer Detail Modal --}}
    @if($showModal && $selectedUser)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>{{ $selectedUser->name }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr><td class="fw-bold">Name</td><td>{{ $selectedUser->name }}</td></tr>
                                <tr><td class="fw-bold">Email</td><td>{{ $selectedUser->email }}</td></tr>
                                <tr><td class="fw-bold">Joined</td><td>{{ $selectedUser->created_at->format('d M Y') }}</td></tr>
                                <tr><td class="fw-bold">Verified</td><td>
                                    @if($selectedUser->email_verified_at)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-warning">No</span>
                                    @endif
                                </td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box bg-primary mb-0">
                                <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Orders</span>
                                    <span class="info-box-number">{{ $selectedUser->orders_count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($selectedUser->orders->count() > 0)
                        <h6 class="fw-bold">Recent Orders</h6>
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr><th>Order #</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                @foreach($selectedUser->orders as $order)
                                    <tr>
                                        <td><code>{{ $order->order_number }}</code></td>
                                        <td>₹{{ number_format($order->total_amount, 2) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $order->status === 'completed' ? 'success' : 'warning' }}">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $order->created_at->format('d M Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted">No orders yet.</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="closeModal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Password Modal --}}
    @if($editPasswordModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>Update Password
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeEditPassword"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" wire:model="newPassword"
                            class="form-control @error('newPassword') is-invalid @enderror"
                            placeholder="Min. 8 characters">
                        @error('newPassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" wire:model="newPasswordConfirmation"
                            class="form-control @error('newPasswordConfirmation') is-invalid @enderror"
                            placeholder="Repeat new password">
                        @error('newPasswordConfirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="closeEditPassword">Cancel</button>
                    <button class="btn btn-warning fw-semibold" wire:click="updatePassword">
                        <i class="fas fa-save me-1"></i>Save Password
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
