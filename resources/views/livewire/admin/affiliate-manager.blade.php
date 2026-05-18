<div>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-6 col-lg-3">
            <div class="info-box bg-info">
                <span class="info-box-icon"><i class="fas fa-handshake"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Affiliates</span>
                    <span class="info-box-number">{{ $stats['total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-success">
                <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active</span>
                    <span class="info-box-number">{{ $stats['active'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-warning">
                <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Pending</span>
                    <span class="info-box-number">{{ $stats['pending'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="info-box bg-primary">
                <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Commission Due</span>
                    <span class="info-box-number">${{ number_format($stats['commission_due'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-8">
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control"
                        placeholder="Search by affiliate name, email, or referral code...">
                </div>
                <div class="col-md-3">
                    <select wire:model.live="status" class="form-select">
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button wire:click="$set('search', '')" class="btn btn-secondary w-100"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Affiliate</th>
                            <th>Code</th>
                            <th>Rate</th>
                            <th>Clicks</th>
                            <th>Conversions</th>
                            <th>Commission</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($affiliates as $affiliate)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $affiliate->user->name }}</div>
                                    <div class="small text-muted">{{ $affiliate->user->email }}</div>
                                </td>
                                <td><code>{{ $affiliate->referral_code }}</code></td>
                                <td>{{ number_format($affiliate->commission_rate, 2) }}%</td>
                                <td>{{ $affiliate->click_count }}</td>
                                <td>{{ $affiliate->conversion_count }}</td>
                                <td>
                                    <div class="small">Due: ${{ number_format($affiliate->approved_commissions, 2) }}</div>
                                    <div class="small text-muted">Paid: ${{ number_format($affiliate->paid_commissions, 2) }}</div>
                                </td>
                                <td>
                                    @php
                                        $badge = match ($affiliate->status) {
                                            'active' => 'success',
                                            'pending' => 'warning text-dark',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">{{ ucfirst($affiliate->status) }}</span>
                                </td>
                                <td>
                                    <button wire:click="editAffiliate({{ $affiliate->id }})" class="btn btn-xs btn-primary">
                                        <i class="fas fa-edit me-1"></i>Manage
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-handshake fa-2x mb-2 d-block"></i>
                                    No affiliate profiles found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($affiliates->hasPages())
            <div class="card-footer">{{ $affiliates->links() }}</div>
        @endif
    </div>

    @if($showModal && $selectedAffiliate)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-handshake me-2"></i>{{ $selectedAffiliate->user->name }}</h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <select wire:model="selectedStatus" class="form-select @error('selectedStatus') is-invalid @enderror">
                                    <option value="pending">Pending</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                                @error('selectedStatus')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Commission Rate %</label>
                                <input type="number" wire:model="commissionRate" class="form-control @error('commissionRate') is-invalid @enderror" min="0" max="100" step="0.01">
                                @error('commissionRate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payout Email</label>
                                <input type="email" wire:model="payoutEmail" class="form-control @error('payoutEmail') is-invalid @enderror">
                                @error('payoutEmail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Referral Code</label>
                                <input type="text" class="form-control" value="{{ $selectedAffiliate->referral_code }}" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Admin Notes</label>
                                <textarea wire:model="adminNotes" rows="3" class="form-control @error('adminNotes') is-invalid @enderror"></textarea>
                                @error('adminNotes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="fw-semibold mb-2">Current performance</div>
                            <div class="row g-2 small">
                                <div class="col-md-3">Clicks: {{ $selectedAffiliate->clicks()->count() }}</div>
                                <div class="col-md-3">Conversions: {{ $selectedAffiliate->conversions()->count() }}</div>
                                <div class="col-md-3">Due: ${{ number_format((float) $selectedAffiliate->conversions()->where('status', 'approved')->sum('commission_amount'), 2) }}</div>
                                <div class="col-md-3">Paid: ${{ number_format((float) $selectedAffiliate->conversions()->where('status', 'paid')->sum('commission_amount'), 2) }}</div>
                            </div>
                        </div>

                        <div class="border rounded p-3">
                            <div class="fw-semibold mb-2">Record payout</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Reference</label>
                                    <input type="text" wire:model="payoutReference" class="form-control" placeholder="Bank ref / PayPal txn / note">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Notes</label>
                                    <input type="text" wire:model="payoutNotes" class="form-control" placeholder="Optional payout note">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between flex-wrap gap-2">
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" wire:click="saveAffiliate">
                                <i class="fas fa-save me-1"></i>Save Settings
                            </button>
                            <button class="btn btn-success" wire:click="recordPayout">
                                <i class="fas fa-money-bill-wave me-1"></i>Mark Approved Commission Paid
                            </button>
                        </div>
                        <button class="btn btn-outline-secondary" wire:click="closeModal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>