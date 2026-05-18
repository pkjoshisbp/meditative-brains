<div>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-sm-4">
            <div class="info-box bg-primary">
                <span class="info-box-icon"><i class="fas fa-tags"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Codes</span>
                    <span class="info-box-number">{{ $stats['total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="info-box bg-success">
                <span class="info-box-icon"><i class="fas fa-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active</span>
                    <span class="info-box-number">{{ $stats['active'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="info-box bg-warning">
                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Expired</span>
                    <span class="info-box-number">{{ $stats['expired'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-badge-percent me-2"></i>Subscription Promo Codes</h5>
            <button wire:click="openCreate" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>New Promo Code
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Validity</th>
                            <th>Usage</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($codes as $code)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $code->code }}</div>
                                    <div class="small text-muted">{{ $code->description ?: 'Subscription discount' }}</div>
                                </td>
                                <td>
                                    @if($code->discount_type === 'percent')
                                        <span class="badge bg-info">{{ number_format($code->discount_value, 0) }}% off</span>
                                    @else
                                        <span class="badge bg-secondary">${{ number_format($code->discount_value, 2) }} off</span>
                                    @endif
                                </td>
                                <td>
                                    @if($code->valid_until)
                                        <div>{{ $code->valid_until->format('d M Y H:i') }}</div>
                                        @if($code->valid_until->isPast())
                                            <div class="small text-danger">Expired</div>
                                        @endif
                                    @else
                                        <span class="text-muted">No expiry</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $code->used_count }} used</div>
                                    <div class="small text-muted">
                                        {{ $code->max_uses ? 'Max ' . $code->max_uses : 'Unlimited' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" wire:click="toggleActive({{ $code->id }})" {{ $code->is_active ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td>
                                    <button wire:click="openEdit({{ $code->id }})" class="btn btn-xs btn-warning me-1">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="deleteCode({{ $code->id }})" onclick="return confirm('Delete promo code {{ $code->code }}?')" class="btn btn-xs btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No promo codes yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.55);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">{{ $isEditing ? 'Edit Promo Code' : 'New Promo Code' }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Code</label>
                                <div class="input-group">
                                    <input type="text" wire:model="code" class="form-control @error('code') is-invalid @enderror" placeholder="SAVE10">
                                    <button class="btn btn-outline-secondary" type="button" wire:click="generateCode">Generate</button>
                                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Type</label>
                                <select wire:model="discount_type" class="form-select @error('discount_type') is-invalid @enderror">
                                    <option value="percent">Percentage</option>
                                    <option value="flat">Flat (USD)</option>
                                </select>
                                @error('discount_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Discount Value</label>
                                <input type="number" wire:model="discount_value" class="form-control @error('discount_value') is-invalid @enderror" min="0.01" step="0.01" placeholder="10">
                                <div class="form-text">For flat discounts, the value is stored in USD and converted to INR using the store rate.</div>
                                @error('discount_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Valid Until</label>
                                <input type="datetime-local" wire:model="valid_until" class="form-control @error('valid_until') is-invalid @enderror">
                                @error('valid_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Max Uses</label>
                                <input type="number" wire:model="max_uses" class="form-control @error('max_uses') is-invalid @enderror" min="1" placeholder="Leave blank for unlimited">
                                @error('max_uses')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" wire:model="is_active" id="promo_active">
                                    <label class="form-check-label" for="promo_active">Active</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <input type="text" wire:model="description" class="form-control @error('description') is-invalid @enderror" placeholder="Optional internal note or campaign name">
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                        <button class="btn btn-primary" wire:click="save">Save Promo Code</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>