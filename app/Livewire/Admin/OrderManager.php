<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use Livewire\WithPagination;
use App\Models\Order;

class OrderManager extends AdminComponent
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $paymentFilter = '';
    public $selectedOrder = null;
    public $showModal = false;

    protected string $pageTitle = 'Orders';
    protected string $pageHeader = 'Orders';

    protected $queryString = ['search', 'statusFilter'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function viewOrder($id)
    {
        $this->selectedOrder = Order::with('user')->findOrFail($id);
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedOrder = null;
    }

    public function updateStatus($id, $status)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => $status]);
        if ($status === 'completed') {
            $order->update(['completed_at' => now()]);
        }
        session()->flash('success', "Order #{$order->order_number} status updated to {$status}.");
    }

    protected function getViewData(): array
    {
        $query = Order::with('user')
            ->when($this->search, function ($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%"));
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->paymentFilter, fn($q) => $q->where('payment_status', $this->paymentFilter))
            ->latest();

        return [
            'orders' => $query->paginate(15),
            'stats'  => [
                'total'     => Order::count(),
                'pending'   => Order::where('status', 'pending')->count(),
                'completed' => Order::where('status', 'completed')->count(),
                'revenue'   => Order::where('payment_status', 'paid')->sum('total_amount'),
            ],
        ];
    }
}
