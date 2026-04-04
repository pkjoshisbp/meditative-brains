<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use Livewire\WithPagination;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;

class SubscriptionPlanManager extends AdminComponent
{
    use WithPagination;

    protected string $pageTitle = 'Subscription Plans';
    protected string $pageHeader = 'Subscription Plans';

    // Modal state
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $editingId = null;

    // Form fields
    public string $name            = '';
    public string $description     = '';
    public string $billing_cycle   = 'monthly';
    public string $price           = '';
    public string $inr_price       = '';
    public string $features        = '';
    public bool   $includes_music_library      = false;
    public bool   $includes_all_tts_categories = false;
    public int    $trial_days   = 0;
    public string $max_products = '';
    public bool   $is_active    = true;
    public bool   $is_featured  = false;
    public int    $sort_order   = 0;

    protected function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'billing_cycle' => 'required|in:monthly,yearly,lifetime',
            'price'         => 'required|numeric|min:0',
            'inr_price'     => 'nullable|numeric|min:0',
            'features'      => 'nullable|string',
            'trial_days'    => 'integer|min:0',
            'max_products'  => 'nullable|integer|min:1',
            'sort_order'    => 'integer|min:0',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $this->editingId   = $id;
        $this->name        = $plan->name;
        $this->description = $plan->description ?? '';
        $this->billing_cycle = $plan->billing_cycle;
        $this->price       = (string) $plan->price;
        $this->inr_price   = (string) ($plan->inr_price ?? '');
        $this->features    = is_array($plan->features) ? implode("\n", $plan->features) : '';
        $this->includes_music_library      = (bool) $plan->includes_music_library;
        $this->includes_all_tts_categories = (bool) $plan->includes_all_tts_categories;
        $this->trial_days   = (int) $plan->trial_days;
        $this->max_products = $plan->max_products !== null ? (string) $plan->max_products : '';
        $this->is_active    = (bool) $plan->is_active;
        $this->is_featured  = (bool) $plan->is_featured;
        $this->sort_order   = (int) $plan->sort_order;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $featuresArray = collect(explode("\n", $this->features))
            ->map(fn($line) => trim($line))
            ->filter()
            ->values()
            ->toArray();

        $data = [
            'name'          => $this->name,
            'description'   => $this->description ?: null,
            'billing_cycle' => $this->billing_cycle,
            'price'         => (float) $this->price,
            'inr_price'     => $this->inr_price !== '' ? (float) $this->inr_price : null,
            'features'      => $featuresArray ?: null,
            'includes_music_library'      => $this->includes_music_library,
            'includes_all_tts_categories' => $this->includes_all_tts_categories,
            'trial_days'    => $this->trial_days,
            'max_products'  => $this->max_products !== '' ? (int) $this->max_products : null,
            'is_active'     => $this->is_active,
            'is_featured'   => $this->is_featured,
            'sort_order'    => $this->sort_order,
        ];

        if ($this->isEditing) {
            SubscriptionPlan::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Plan updated successfully.');
        } else {
            SubscriptionPlan::create($data);
            session()->flash('success', 'Plan created successfully.');
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
        $status = $plan->fresh()->is_active ? 'activated' : 'deactivated';
        session()->flash('success', "Plan \"{$plan->name}\" {$status}.");
    }

    public function deletePlan(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->delete();
        session()->flash('success', "Plan \"{$plan->name}\" deleted.");
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->name            = '';
        $this->description     = '';
        $this->billing_cycle   = 'monthly';
        $this->price           = '';
        $this->inr_price       = '';
        $this->features        = '';
        $this->includes_music_library      = false;
        $this->includes_all_tts_categories = false;
        $this->trial_days   = 0;
        $this->max_products = '';
        $this->is_active    = true;
        $this->is_featured  = false;
        $this->sort_order   = 0;
        $this->isEditing    = false;
        $this->editingId    = null;
        $this->resetErrorBag();
    }

    protected function getViewData(): array
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('created_at')->get();

        // Attach stats
        $plans->each(function ($plan) {
            $plan->active_subscribers = Subscription::where('plan_type', $plan->slug)
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->count();
        });

        return [
            'plans' => $plans,
            'stats' => [
                'total'    => $plans->count(),
                'active'   => $plans->where('is_active', true)->count(),
                'featured' => $plans->where('is_featured', true)->count(),
            ],
        ];
    }
}
