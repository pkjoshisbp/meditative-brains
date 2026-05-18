@extends('account.layout')
@section('title', 'Affiliate')

@section('account-content')
<div>
    <h2 class="fw-bold mb-1">Affiliate Program</h2>
    <p class="text-muted mb-4">Share your referral link, track conversions, and earn commission on all supported purchases, including student-priced sales.</p>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(!$affiliateProfile)
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">Apply to become an affiliate</h5>
                <p class="text-muted">Any customer type can be an affiliate. Students, school admins, and regular customers all use the same system once approved.</p>

                <form method="POST" action="{{ route('account.affiliate.apply') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payout Email</label>
                            <input type="email" name="payout_email" class="form-control @error('payout_email') is-invalid @enderror" value="{{ old('payout_email', $user->email) }}">
                            @error('payout_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Application Notes</label>
                            <textarea name="application_notes" rows="4" class="form-control @error('application_notes') is-invalid @enderror" placeholder="Tell us about your audience, school, student group, or promotion channel.">{{ old('application_notes') }}</textarea>
                            @error('application_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="fas fa-paper-plane me-1"></i>Submit Affiliate Application
                    </button>
                </form>
            </div>
        </div>
    @else
        @php
            $statusClass = match ($affiliateProfile->status) {
                'active' => 'success',
                'pending' => 'warning text-dark',
                default => 'secondary',
            };
            $referralUrl = url('/?aff=' . $affiliateProfile->referral_code);
        @endphp

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h5 class="mb-0">Affiliate Status</h5>
                        <span class="badge bg-{{ $statusClass }}">{{ ucfirst($affiliateProfile->status) }}</span>
                    </div>
                    <div class="small text-muted">Custom commission rate: {{ number_format($affiliateProfile->commission_rate, 2) }}%</div>
                </div>
                @if($affiliateProfile->status === 'active')
                    <div class="text-break">
                        <div class="small text-muted">Referral link</div>
                        <code>{{ $referralUrl }}</code>
                    </div>
                @endif
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center py-3"><div class="card-body"><div class="display-6 fw-bold text-primary mb-1">{{ $stats['clicks'] }}</div><div class="small text-muted">Clicks</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center py-3"><div class="card-body"><div class="display-6 fw-bold text-success mb-1">{{ $stats['conversions'] }}</div><div class="small text-muted">Conversions</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center py-3"><div class="card-body"><div class="display-6 fw-bold text-info mb-1">${{ number_format($stats['approved_commissions'], 2) }}</div><div class="small text-muted">Commission Due</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center py-3"><div class="card-body"><div class="display-6 fw-bold text-secondary mb-1">${{ number_format($stats['paid_commissions'], 2) }}</div><div class="small text-muted">Commission Paid</div></div></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Affiliate Settings</div>
            <div class="card-body">
                <form method="POST" action="{{ route('account.affiliate.apply') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payout Email</label>
                            <input type="email" name="payout_email" class="form-control @error('payout_email') is-invalid @enderror" value="{{ old('payout_email', $affiliateProfile->payout_email ?: $user->email) }}">
                            @error('payout_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="application_notes" rows="3" class="form-control @error('application_notes') is-invalid @enderror">{{ old('application_notes', $affiliateProfile->application_notes) }}</textarea>
                            @error('application_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary mt-3">
                        <i class="fas fa-save me-1"></i>Update Affiliate Details
                    </button>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Recent Conversions</div>
                    <div class="card-body p-0">
                        @if($recentConversions->isEmpty())
                            <div class="text-center text-muted py-5">No conversions yet.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr><th>Type</th><th>Gross</th><th>Commission</th><th>Status</th><th>Date</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentConversions as $conversion)
                                            <tr>
                                                <td>{{ ucwords(str_replace('_', ' ', $conversion->conversion_type)) }}</td>
                                                <td>{{ $conversion->currency }} {{ number_format($conversion->gross_amount, 2) }}</td>
                                                <td>{{ $conversion->currency }} {{ number_format($conversion->commission_amount, 2) }}</td>
                                                <td><span class="badge bg-{{ $conversion->status === 'paid' ? 'success' : 'warning text-dark' }}">{{ ucfirst($conversion->status) }}</span></td>
                                                <td class="small text-muted">{{ $conversion->created_at->format('d M Y') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Payout History</div>
                    <div class="card-body p-0">
                        @if($recentPayouts->isEmpty())
                            <div class="text-center text-muted py-5">No payouts recorded yet.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr><th>Amount</th><th>Reference</th><th>Date</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentPayouts as $payout)
                                            <tr>
                                                <td>{{ $payout->currency }} {{ number_format($payout->amount, 2) }}</td>
                                                <td>{{ $payout->reference ?: '—' }}</td>
                                                <td class="small text-muted">{{ optional($payout->paid_at)->format('d M Y') ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection