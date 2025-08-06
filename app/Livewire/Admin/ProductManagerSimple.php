<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductManagerSimple extends Component
{
    use WithPagination;

    public $name = '';
    public $category_id = '';
    public $price = '';
    public $showForm = false;
    public $editingProduct = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'category_id' => 'required|exists:product_categories,id',
        'price' => 'required|numeric|min:0',
    ];

    public function mount()
    {
        // Simple mount
    }

    public function render()
    {
        $categories = ProductCategory::all();
        $products = Product::with('category')->latest()->paginate(10);
        
        return view('livewire.admin.product-manager-simple', compact('categories', 'products'));
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($productId)
    {
        $product = Product::findOrFail($productId);
        $this->editingProduct = $product;
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->price = $product->price;
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->editingProduct) {
            $this->editingProduct->update([
                'name' => $this->name,
                'category_id' => $this->category_id,
                'price' => $this->price,
            ]);
            session()->flash('message', 'Product updated successfully.');
        } else {
            Product::create([
                'name' => $this->name,
                'category_id' => $this->category_id,
                'price' => $this->price,
            ]);
            session()->flash('message', 'Product created successfully.');
        }

        $this->resetForm();
    }

    public function delete($productId)
    {
        Product::findOrFail($productId)->delete();
        session()->flash('message', 'Product deleted successfully.');
    }

    public function cancel()
    {
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->name = '';
        $this->category_id = '';
        $this->price = '';
        $this->showForm = false;
        $this->editingProduct = false;
        $this->resetErrorBag();
    }
}
