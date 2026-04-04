<div>
    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" wire:key="flash-success">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row mb-4">
        <div class="col-sm-4">
            <div class="info-box bg-primary">
                <span class="info-box-icon"><i class="fas fa-layer-group"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Plans</span>
                    <span class="info-box-number">{{ $stats['total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="info-box bg-success">
                <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active Plans</span>
                    <span class="info-box-number">{{ $stats['active'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="info-box bg-warning">
                <span class="info-box-icon"><i class="fas fa-star"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Featured Plans</span>
                    <span class="info-box-number">{{ $stats['featured'] }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Subscription Plans</h5>
            <button wire:click="openCreate" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>New Plan
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Plan Name</th>
                            <th>Billing</th>
                            <th>Price (INR)</th>
                            <th>Features</th>
                            <th>Subscribers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td class="text-muted small">{{ $plan->sort_order ?: '—' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $plan->name }}</div>
                                    <div class="small text-muted">{{ $plan->slug }}</div>
                                    @if($plan->is_featured)
                                        <span class="badge bg-warning text-dark mt-1"><i class="fas fa-star me-1"></i>Featured</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $plan->billing_cycle === 'monthly' ? 'info' : ($plan->billing_cycle === 'yearly' ? 'primary' : 'secondary') }}">
                                        {{ ucfirst($plan->billing_cycle) }}
                                    </span>
                                    @if($plan->trial_days > 0)
                                        <div class="small text-muted mt-1">{{ $plan->trial_days }}d trial</div>
                                    @endif
                                </td>
                                <td>
                                    @if($plan->inr_price)
                                        <div class="fw-semibold">₹{{ number_format($plan->inr_price, 0) }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    @if($plan->price > 0)
                                        <div class="small text-muted">${{ number_format($plan->price, 2) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="small">
                                        @if($plan->includes_music_library)
                                            <span class="badge bg-light text-dark mb-1 d-block text-start">
                                                <i class="fas fa-music me-1 text-primary"></i>Music Library
                                            </span>
                                        @endif
                                        @if($plan->includes_all_tts_categories)
                                            <span class="badge bg-light text-dark mb-1 d-block text-start">
                                                <i class="fas fa-comments me-1 text-success"></i>All TTS
                                            </span>
                                        @endif
                                        @if($plan->max_products)
                                            <span class="badge bg-light text-dark d-block text-start">
                                                <i class="fas fa-box me-1 text-warning"></i>Max {{ $plan->max_products }} products
                                            </span>
                                        @endif
                                        @if(!$plan->includes_music_library && !$plan->includes_all_tts_categories && !$plan->max_products)
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $plan->active_subscribers > 0 ? 'success' : 'secondary' }}">
                                        {{ $plan->active_subscribers }}
                                    </span>
                                </td>
                                <td>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox"
                                            wire:click="toggleActive({{ $plan->id }})"
                                            {{ $plan->is_active ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td>
                                    <button wire:click="openEdit({{ $plan->id }})"
                                        class="btn btn-xs btn-warning me-1" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="deletePlan({{ $plan->id }})"
                                        onclick="return confirm('Delete plan \'{{ addslashes($plan->name) }}\'? This cannot be undone.')"
                                        class="btn btn-xs btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fas fa-layer-group fa-3x mb-3 d-block opacity-25"></i>
                                    No subscription plans yet.
                                    <br>
                                    <button wire:click="openCreate" class="btn btn-primary btn-sm mt-3">
                                        <i class="fas fa-plus me-1"></i>Create First Plan
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Create / Edit Modal --}}
    @if($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.55);">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-{{ $isEditing ? 'edit' : 'plus' }} me-2"></i>
                        {{ $isEditing ? 'Edit Plan' : 'New Subscription Plan' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Name --}}
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Plan Name <span class="text-danger">*</span></label>
                            <input type="text" wire:model="name"
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="e.g. Premium Monthly">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Billing Cycle --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Billing Cycle <span class="text-danger">*</span></label>
                            <select wire:model="billing_cycle" class="form-select">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                                <option value="lifetime">Lifetime</option>
                            </select>
                        </div>

                        {{-- Prices --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Price INR (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" wire:model="inr_price"
                                    class="form-control @error('inr_price') is-invalid @enderror"
                                    placeholder="0" min="0" step="1">
                                @error('inr_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Price USD ($)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" wire:model="price"
                                    class="form-control @error('price') is-invalid @enderror"
                                    placeholder="0.00" min="0" step="0.01">
                                @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Description --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea wire:model="description" rows="2"
                                class="form-control @error('description') is-invalid @enderror"
                                placeholder="Short description of the plan..."></textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Features --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Features</label>
                            <textarea wire:model="features" rows="4"
                                class="form-control @error('features') is-invalid @enderror"
                                placeholder="One feature per line, e.g.:&#10;Access to all audio content&#10;Download up to 5 tracks/month&#10;Cancel anytime"></textarea>
                            <div class="form-text">One feature per line. Displayed on the pricing page.</div>
                            @error('features')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Access checkboxes --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Access Permissions</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                        wire:model="includes_music_library" id="inc_music">
                                    <label class="form-check-label" for="inc_music">
                                        <i class="fas fa-music text-primary me-1"></i>Includes Music Library
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                        wire:model="includes_all_tts_categories" id="inc_tts">
                                    <label class="form-check-label" for="inc_tts">
                                        <i class="fas fa-comments text-success me-1"></i>Includes All TTS Categories
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Misc --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Trial Days</label>
                            <input type="number" wire:model="trial_days"
                                class="form-control @error('trial_days') is-invalid @enderror"
                                min="0" placeholder="0">
                            @error('trial_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Products</label>
                            <input type="number" wire:model="max_products"
                                class="form-control @error('max_products') is-invalid @enderror"
                                min="1" placeholder="Unlimited">
                            <div class="form-text">Leave blank for unlimited.</div>
                            @error('max_products')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" wire:model="sort_order"
                                class="form-control" min="0" placeholder="0">
                        </div>

                        {{-- Flags --}}
                        <div class="col-12">
                            <div class="d-flex gap-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                        wire:model="is_active" id="plan_active">
                                    <label class="form-check-label fw-semibold" for="plan_active">Active (visible to users)</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                        wire:model="is_featured" id="plan_featured">
                                    <label class="form-check-label fw-semibold" for="plan_featured">
                                        <i class="fas fa-star text-warning me-1"></i>Featured (highlighted on pricing page)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="button" class="btn btn-primary fw-semibold" wire:click="save">
                        <i class="fas fa-save me-1"></i>{{ $isEditing ? 'Update Plan' : 'Create Plan' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
