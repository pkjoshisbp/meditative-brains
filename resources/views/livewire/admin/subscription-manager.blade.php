<div>
    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" wire:key="flash-ok">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row mb-4">
        <div class="col-6 col-lg-3">
            <div class="info-box bg-primary">
                <span class="info-box-icon"><i class="fas fa-id-card-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total</span>
                    <span class="info-box-number">{{ $stats['total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-success">
                <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active</span>
                    <span class="info-box-number">{{ $stats['active'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-warning">
                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Expired</span>
                    <span class="info-box-number">{{ $stats['expired'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-info">
                <span class="info-box-icon"><i class="fas fa-user-shield"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Manual Grants</span>
                    <span class="info-box-number">{{ $stats['manual'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter + Action bar --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex gap-2 flex-wrap flex-grow-1">
                <input type="text" wire:model.live.debounce.300ms="search"
                    class="form-control form-control-sm" style="max-width:220px"
                    placeholder="Search by name or email…">
                <select wire:model.live="statusFilter" class="form-select form-select-sm" style="max-width:160px">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="expired">Expired</option>
                    <option value="superseded">Superseded</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button wire:click="openPlanModal" class="btn btn-success btn-sm">
                    <i class="fas fa-crown me-1"></i>Assign Plan
                </button>
                <button wire:click="openProductModal" class="btn btn-primary btn-sm">
                    <i class="fas fa-box me-1"></i>Grant Product
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Price</th>
                            <th>Started</th>
                            <th>Expires</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscriptions as $sub)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $sub->user?->name ?? '—' }}</div>
                                    <div class="small text-muted">{{ $sub->user?->email }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-primary">{{ $sub->plan_type }}</span>
                                    @if($sub->is_trial)
                                        <span class="badge bg-info ms-1">Trial</span>
                                    @endif
                                </td>
                                <td>
                                    @if($sub->price > 0)
                                        ₹{{ number_format($sub->price, 0) }}
                                    @else
                                        <span class="text-muted">Free</span>
                                    @endif
                                </td>
                                <td class="small">{{ $sub->starts_at?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <div class="small {{ $sub->ends_at && $sub->ends_at->isPast() ? 'text-danger fw-semibold' : '' }}">
                                        {{ $sub->ends_at?->format('d M Y') ?? '—' }}
                                    </div>
                                    @if($sub->ends_at && $sub->ends_at->isFuture())
                                        <div class="small text-success">{{ $sub->ends_at->diffForHumans() }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($sub->payment_method === 'admin_manual')
                                        <span class="badge bg-secondary">Admin</span>
                                    @else
                                        <span class="small text-muted">{{ $sub->payment_method }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $isActive = $sub->status === 'active' && $sub->ends_at?->isFuture();
                                    @endphp
                                    <span class="badge bg-{{ $isActive ? 'success' : ($sub->status === 'cancelled' ? 'danger' : 'secondary') }}">
                                        {{ ucfirst($sub->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($isActive)
                                        <button wire:click="openExtend({{ $sub->id }})"
                                            class="btn btn-xs btn-warning me-1" title="Extend">
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                        <button wire:click="openCancelConfirm({{ $sub->id }})"
                                            class="btn btn-xs btn-danger" title="Cancel">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fas fa-id-card-alt fa-3x d-block mb-3 opacity-25"></i>
                                    No subscriptions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($subscriptions->hasPages())
            <div class="card-footer bg-white">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>

    {{-- ────────────────────────────────────────────────
         ASSIGN PLAN MODAL
    ──────────────────────────────────────────────── --}}
    @if($showPlanModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.55);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-crown me-2"></i>Assign Subscription Plan</h5>
                    <button type="button" class="btn-close btn-close-white"
                        wire:click="$set('showPlanModal', false)"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        {{-- User search + select --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">User <span class="text-danger">*</span></label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" wire:model.live.debounce.300ms="planUserSearch"
                                    class="form-control" placeholder="Search user by name or email…">
                            </div>
                            <select wire:model="planUserId"
                                class="form-select @error('planUserId') is-invalid @enderror"
                                size="4">
                                <option value="">— Select a user —</option>
                                @foreach($modalUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @error('planUserId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Plan --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Plan <span class="text-danger">*</span></label>
                            <select wire:model.live="planSlug"
                                class="form-select @error('planSlug') is-invalid @enderror">
                                <option value="">— Select a plan —</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->slug }}">
                                        {{ $plan->name }}
                                        ({{ ucfirst($plan->billing_cycle) }}
                                        @if($plan->inr_price) · ₹{{ number_format($plan->inr_price,0) }}@endif)
                                    </option>
                                @endforeach
                            </select>
                            @error('planSlug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Duration --}}
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Duration (days) <span class="text-danger">*</span></label>
                            <input type="number" wire:model="planDuration"
                                class="form-control @error('planDuration') is-invalid @enderror"
                                min="1" placeholder="30">
                            @error('planDuration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Price override --}}
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Price (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" wire:model="planPrice"
                                    class="form-control" min="0" placeholder="0">
                            </div>
                            <div class="form-text">Set 0 for a free/manual grant.</div>
                        </div>

                        {{-- Trial toggle --}}
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" wire:model="planIsTrial" id="plan_trial">
                                <label class="form-check-label" for="plan_trial">Mark as Trial Subscription</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="$set('showPlanModal', false)">Cancel</button>
                    <button class="btn btn-success fw-semibold" wire:click="assignPlan">
                        <i class="fas fa-crown me-1"></i>Assign Plan
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ────────────────────────────────────────────────
         GRANT PRODUCT MODAL
    ──────────────────────────────────────────────── --}}
    @if($showProductModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.55);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-box me-2"></i>Grant Product Access</h5>
                    <button type="button" class="btn-close btn-close-white"
                        wire:click="$set('showProductModal', false)"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        {{-- User search + select --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">User <span class="text-danger">*</span></label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" wire:model.live.debounce.300ms="productUserSearch"
                                    class="form-control" placeholder="Search user by name or email…">
                            </div>
                            <select wire:model="productUserId"
                                class="form-select @error('productUserId') is-invalid @enderror"
                                size="4">
                                <option value="">— Select a user —</option>
                                @foreach($productModalUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @error('productUserId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Product search + select --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" wire:model.live.debounce.300ms="productSearch"
                                    class="form-control" placeholder="Search product by name…">
                            </div>
                            <select wire:model="productId"
                                class="form-select @error('productId') is-invalid @enderror"
                                size="5">
                                <option value="">— Select a product —</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                                @endforeach
                            </select>
                            @error('productId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0 py-2 small">
                                <i class="fas fa-info-circle me-2"></i>
                                This creates a complimentary ₹0 completed order granting the user access to the selected product.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="$set('showProductModal', false)">Cancel</button>
                    <button class="btn btn-primary fw-semibold" wire:click="grantProduct">
                        <i class="fas fa-gift me-1"></i>Grant Access
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ────────────────────────────────────────────────
         CANCEL / EXTEND MODAL
    ──────────────────────────────────────────────── --}}
    @if($showActionModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.55);">
        <div class="modal-dialog">
            <div class="modal-content">
                @if($actionType === 'cancel')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-ban me-2"></i>Cancel Subscription</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeActionModal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to cancel this subscription? The user will lose access when the period ends.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="closeActionModal">No, Keep It</button>
                    <button class="btn btn-danger fw-semibold" wire:click="performAction">
                        <i class="fas fa-ban me-1"></i>Yes, Cancel
                    </button>
                </div>
                @else
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Extend Subscription</h5>
                    <button type="button" class="btn-close" wire:click="closeActionModal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Extend by how many days?</label>
                    <input type="number" wire:model="extendDays"
                        class="form-control @error('extendDays') is-invalid @enderror"
                        min="1" max="3650" placeholder="30">
                    @error('extendDays')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text mt-2">The extension is added from the current expiry date (or today if already expired).</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="closeActionModal">Cancel</button>
                    <button class="btn btn-warning fw-semibold" wire:click="performAction">
                        <i class="fas fa-calendar-plus me-1"></i>Extend Subscription
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
