<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use App\Models\AffiliateProfile;
use App\Services\AffiliateService;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class AffiliateManager extends AdminComponent
{
    use WithPagination;

    protected string $pageTitle = 'Affiliates';
    protected string $pageHeader = 'Affiliate Management';

    public string $search = '';
    public string $status = 'all';
    public bool $showModal = false;
    public ?int $selectedAffiliateId = null;
    public string $selectedStatus = 'pending';
    public string $commissionRate = '';
    public string $payoutEmail = '';
    public string $adminNotes = '';
    public string $payoutReference = '';
    public string $payoutNotes = '';

    protected $queryString = ['search', 'status'];
    protected $paginationTheme = 'bootstrap';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function editAffiliate(int $affiliateId): void
    {
        $affiliate = AffiliateProfile::with('user')->findOrFail($affiliateId);
        $this->selectedAffiliateId = $affiliate->id;
        $this->selectedStatus = $affiliate->status;
        $this->commissionRate = (string) $affiliate->commission_rate;
        $this->payoutEmail = (string) ($affiliate->payout_email ?? '');
        $this->adminNotes = (string) ($affiliate->admin_notes ?? '');
        $this->payoutReference = '';
        $this->payoutNotes = '';
        $this->showModal = true;
        $this->resetErrorBag();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedAffiliateId = null;
        $this->selectedStatus = 'pending';
        $this->commissionRate = '';
        $this->payoutEmail = '';
        $this->adminNotes = '';
        $this->payoutReference = '';
        $this->payoutNotes = '';
    }

    public function saveAffiliate(): void
    {
        $this->validate([
            'selectedStatus' => 'required|in:pending,active,suspended',
            'commissionRate' => 'required|numeric|min:0|max:100',
            'payoutEmail' => 'nullable|email|max:255',
            'adminNotes' => 'nullable|string|max:2000',
        ]);

        $affiliate = AffiliateProfile::findOrFail($this->selectedAffiliateId);
        $affiliate->update([
            'status' => $this->selectedStatus,
            'commission_rate' => (float) $this->commissionRate,
            'payout_email' => $this->payoutEmail !== '' ? $this->payoutEmail : null,
            'admin_notes' => $this->adminNotes !== '' ? $this->adminNotes : null,
            'approved_at' => $this->selectedStatus === 'active' ? ($affiliate->approved_at ?? now()) : null,
            'approved_by' => $this->selectedStatus === 'active' ? Auth::id() : null,
        ]);

        session()->flash('success', "Affiliate settings updated for {$affiliate->user->name}.");
        $this->closeModal();
    }

    public function recordPayout(AffiliateService $affiliateService): void
    {
        $affiliate = AffiliateProfile::findOrFail($this->selectedAffiliateId);
        $payout = $affiliateService->payOutstanding(
            $affiliate,
            $this->payoutReference !== '' ? $this->payoutReference : null,
            $this->payoutNotes !== '' ? $this->payoutNotes : null,
        );

        if (! $payout) {
            session()->flash('success', 'No approved unpaid commissions were available for payout.');
            $this->closeModal();
            return;
        }

        session()->flash('success', "Payout recorded for {$affiliate->user->name}: {$payout->currency} {$payout->amount}.");
        $this->closeModal();
    }

    protected function getViewData(): array
    {
        $affiliates = AffiliateProfile::with('user')
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->search !== '', function ($query) {
                $query->where(function ($innerQuery) {
                    $innerQuery->whereHas('user', function ($inner) {
                        $inner->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%');
                    })->orWhere('referral_code', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate(15);

        $affiliates->getCollection()->transform(function ($affiliate) {
            $affiliate->click_count = $affiliate->clicks()->count();
            $affiliate->conversion_count = $affiliate->conversions()->count();
            $affiliate->gross_sales = (float) $affiliate->conversions()->sum('gross_amount');
            $affiliate->approved_commissions = (float) $affiliate->conversions()->where('status', 'approved')->sum('commission_amount');
            $affiliate->paid_commissions = (float) $affiliate->conversions()->where('status', 'paid')->sum('commission_amount');
            return $affiliate;
        });

        return [
            'affiliates' => $affiliates,
            'selectedAffiliate' => $this->selectedAffiliateId ? AffiliateProfile::with(['user', 'conversions', 'payouts'])->find($this->selectedAffiliateId) : null,
            'stats' => [
                'total' => AffiliateProfile::count(),
                'active' => AffiliateProfile::where('status', 'active')->count(),
                'pending' => AffiliateProfile::where('status', 'pending')->count(),
                'commission_due' => (float) \App\Models\AffiliateConversion::where('status', 'approved')->sum('commission_amount'),
            ],
        ];
    }
}