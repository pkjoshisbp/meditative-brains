<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use App\Models\PromoCode;
use Illuminate\Support\Str;

class PromoCodeManager extends AdminComponent
{
    protected string $pageTitle = 'Promo Codes';
    protected string $pageHeader = 'Promo Codes';

    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $description = '';
    public string $discount_type = 'percent';
    public string $discount_value = '';
    public string $valid_until = '';
    public string $max_uses = '';
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:flat,percent',
            'discount_value' => 'required|numeric|min:0.01',
            'valid_until' => 'nullable|date',
            'max_uses' => 'nullable|integer|min:1',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->code = $this->generateSuggestedCode();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $promo = PromoCode::findOrFail($id);
        $this->editingId = $promo->id;
        $this->code = $promo->code;
        $this->description = $promo->description ?? '';
        $this->discount_type = $promo->discount_type;
        $this->discount_value = (string) $promo->discount_value;
        $this->valid_until = $promo->valid_until?->format('Y-m-d\TH:i') ?? '';
        $this->max_uses = $promo->max_uses !== null ? (string) $promo->max_uses : '';
        $this->is_active = (bool) $promo->is_active;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function generateCode(): void
    {
        $this->code = $this->generateSuggestedCode();
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'code' => strtoupper(trim($this->code)),
            'description' => $this->description !== '' ? $this->description : null,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'valid_until' => $this->valid_until !== '' ? $this->valid_until : null,
            'max_uses' => $this->max_uses !== '' ? (int) $this->max_uses : null,
            'is_active' => $this->is_active,
            'applies_to' => 'subscriptions',
        ];

        if ($this->isEditing) {
            PromoCode::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Promo code updated successfully.');
        } else {
            PromoCode::create($data + ['used_count' => 0]);
            session()->flash('success', 'Promo code created successfully.');
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $promo = PromoCode::findOrFail($id);
        $promo->update(['is_active' => ! $promo->is_active]);
        session()->flash('success', 'Promo code status updated.');
    }

    public function deleteCode(int $id): void
    {
        PromoCode::findOrFail($id)->delete();
        session()->flash('success', 'Promo code deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function getViewData(): array
    {
        $codes = PromoCode::orderByDesc('created_at')->get();

        return [
            'codes' => $codes,
            'stats' => [
                'total' => $codes->count(),
                'active' => $codes->where('is_active', true)->count(),
                'expired' => $codes->filter(fn ($code) => $code->valid_until && $code->valid_until->isPast())->count(),
            ],
        ];
    }

    private function resetForm(): void
    {
        $this->code = '';
        $this->description = '';
        $this->discount_type = 'percent';
        $this->discount_value = '';
        $this->valid_until = '';
        $this->max_uses = '';
        $this->is_active = true;
        $this->showModal = false;
        $this->isEditing = false;
        $this->editingId = null;
        $this->resetErrorBag();
    }

    private function generateSuggestedCode(): string
    {
        do {
            $code = 'SAVE' . Str::upper(Str::random(6));
        } while (PromoCode::where('code', $code)->exists());

        return $code;
    }
}