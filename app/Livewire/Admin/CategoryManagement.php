<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\TtsSourceCategory;
use Illuminate\Support\Facades\Log; // loaded

class CategoryManagement extends Component
{
    public $categories = [];
    public $categoryName = '';
    public $editingCategoryId = null;
    public $editingCategoryName = '';

    protected $rules = [
        'categoryName' => 'required|string|min:2|max:100',
        'editingCategoryName' => 'required|string|min:2|max:100',
    ];

    public function mount()
    {
        $this->loadCategories();
    }

    public function loadCategories()
    {
        $this->categories = TtsSourceCategory::orderBy('category')
            ->get()
            ->map(fn($c) => ['_id' => (string)$c->id, 'mongo_id' => $c->mongo_id, 'category' => $c->category])
            ->toArray();
    }

    public function addCategory()
    {
        $this->validate(['categoryName' => 'required|string|min:2|max:100']);

        try {
            TtsSourceCategory::create(['category' => $this->categoryName]);
            session()->flash('success', 'Category added successfully!');
            $this->categoryName = '';
            $this->loadCategories();
        } catch (\Exception $e) {
            Log::error('Exception adding category: ' . $e->getMessage());
            session()->flash('error', 'Error adding category: ' . $e->getMessage());
        }
    }

    public function editCategory($categoryId)
    {
        $category = collect($this->categories)->firstWhere('_id', (string)$categoryId);
        if ($category) {
            $this->editingCategoryId = $categoryId;
            $this->editingCategoryName = $category['category'];
        }
    }

    public function updateCategory()
    {
        $this->validate(['editingCategoryName' => 'required|string|min:2|max:100']);

        try {
            TtsSourceCategory::where('id', $this->editingCategoryId)
                ->update(['category' => $this->editingCategoryName]);
            session()->flash('success', 'Category updated successfully!');
            $this->cancelEdit();
            $this->loadCategories();
        } catch (\Exception $e) {
            Log::error('Exception updating category: ' . $e->getMessage());
            session()->flash('error', 'Error updating category: ' . $e->getMessage());
        }
    }

    public function deleteCategory($categoryId)
    {
        try {
            TtsSourceCategory::where('id', $categoryId)->delete();
            session()->flash('success', 'Category deleted successfully!');
            $this->loadCategories();
        } catch (\Exception $e) {
            Log::error('Exception deleting category: ' . $e->getMessage());
            session()->flash('error', 'Error deleting category: ' . $e->getMessage());
        }
    }

    public function cancelEdit()
    {
        $this->editingCategoryId = null;
        $this->editingCategoryName = '';
    }

    /** MySQL is always available — just counts categories. */
    public function testConnection()
    {
        $count = TtsSourceCategory::count();
        session()->flash('success', "MySQL connected. Found {$count} categories.");
    }

    public function render()
    {
        return view('livewire.admin.category-management')
            ->extends('adminlte::page')
            ->section('content');
    }
}
