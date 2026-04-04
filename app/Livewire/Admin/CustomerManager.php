<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\Hash;

class CustomerManager extends AdminComponent
{
    use WithPagination;

    public $search = '';
    public $selectedUser = null;
    public $showModal = false;

    // Edit password modal
    public $editPasswordModal = false;
    public $editUserId = null;
    public $newPassword = '';
    public $newPasswordConfirmation = '';

    protected string $pageTitle = 'Customers';
    protected string $pageHeader = 'Customers';

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function viewCustomer($id)
    {
        $this->selectedUser = User::withCount('orders')
            ->with(['orders' => fn($q) => $q->latest()->limit(5)])
            ->findOrFail($id);
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedUser = null;
    }

    public function deleteCustomer($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        session()->flash('success', "Customer \"{$user->name}\" has been deleted.");
    }

    public function openEditPassword($id)
    {
        $this->editUserId = $id;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->resetErrorBag();
        $this->editPasswordModal = true;
    }

    public function closeEditPassword()
    {
        $this->editPasswordModal = false;
        $this->editUserId = null;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
    }

    public function updatePassword()
    {
        $this->validate([
            'newPassword'             => 'required|min:8',
            'newPasswordConfirmation' => 'required|same:newPassword',
        ], [
            'newPassword.required'             => 'New password is required.',
            'newPassword.min'                  => 'Password must be at least 8 characters.',
            'newPasswordConfirmation.required' => 'Please confirm the new password.',
            'newPasswordConfirmation.same'     => 'Passwords do not match.',
        ]);

        User::findOrFail($this->editUserId)->update([
            'password' => Hash::make($this->newPassword),
        ]);

        $this->closeEditPassword();
        session()->flash('success', 'Password updated successfully.');
    }

    protected function getViewData(): array
    {
        $customers = User::withCount('orders')
            ->when($this->search, function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            })
            ->latest()
            ->paginate(15);

        return [
            'customers' => $customers,
            'stats'     => [
                'total'       => User::count(),
                'new_month'   => User::whereMonth('created_at', now()->month)->count(),
                'with_orders' => User::has('orders')->count(),
            ],
        ];
    }
}
