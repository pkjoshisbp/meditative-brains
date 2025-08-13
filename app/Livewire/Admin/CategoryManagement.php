<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

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
        try {
            $response = Http::get('https://meditative-brains.com:3001/api/category');
            
            if ($response->successful()) {
                $this->categories = $response->json();
                \Log::info('Categories loaded successfully', [
                    'count' => count($this->categories)
                ]);
            } else {
                \Log::error('Failed to load categories', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                $this->categories = [];
                session()->flash('error', 'Failed to load categories from API');
            }
        } catch (\Exception $e) {
            \Log::error('Exception loading categories: ' . $e->getMessage());
            $this->categories = [];
            session()->flash('error', 'Connection error: ' . $e->getMessage());
        }
    }

    public function addCategory()
    {
        $this->validate([
            'categoryName' => 'required|string|min:2|max:100'
        ]);

        try {
            $response = Http::post('https://meditative-brains.com:3001/api/category', [
                'category' => $this->categoryName,
                'language' => 'en-US' // Default language
            ]);

            if ($response->successful()) {
                session()->flash('success', 'Category added successfully!');
                $this->categoryName = '';
                $this->loadCategories();
            } else {
                \Log::error('Failed to add category', [
                    'name' => $this->categoryName,
                    'response' => $response->body()
                ]);
                session()->flash('error', 'Failed to add category: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('Exception adding category: ' . $e->getMessage());
            session()->flash('error', 'Error adding category: ' . $e->getMessage());
        }
    }

    public function editCategory($categoryId)
    {
        $category = collect($this->categories)->firstWhere('_id', $categoryId);
        if ($category) {
            $this->editingCategoryId = $categoryId;
            $this->editingCategoryName = $category['category'];
        }
    }

    public function updateCategory()
    {
        $this->validate([
            'editingCategoryName' => 'required|string|min:2|max:100'
        ]);

        try {
            $response = Http::put("https://meditative-brains.com:3001/api/category/{$this->editingCategoryId}", [
                'category' => $this->editingCategoryName,
                'language' => 'en-US'
            ]);

            if ($response->successful()) {
                session()->flash('success', 'Category updated successfully!');
                $this->cancelEdit();
                $this->loadCategories();
            } else {
                \Log::error('Failed to update category', [
                    'id' => $this->editingCategoryId,
                    'name' => $this->editingCategoryName,
                    'response' => $response->body()
                ]);
                session()->flash('error', 'Failed to update category: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('Exception updating category: ' . $e->getMessage());
            session()->flash('error', 'Error updating category: ' . $e->getMessage());
        }
    }

    public function deleteCategory($categoryId)
    {
        try {
            $response = Http::delete("https://meditative-brains.com:3001/api/category/{$categoryId}");

            if ($response->successful()) {
                session()->flash('success', 'Category deleted successfully!');
                $this->loadCategories();
            } else {
                \Log::error('Failed to delete category', [
                    'id' => $categoryId,
                    'response' => $response->body()
                ]);
                session()->flash('error', 'Failed to delete category: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('Exception deleting category: ' . $e->getMessage());
            session()->flash('error', 'Error deleting category: ' . $e->getMessage());
        }
    }

    public function cancelEdit()
    {
        $this->editingCategoryId = null;
        $this->editingCategoryName = '';
    }

    public function testConnection()
    {
        try {
            $response = Http::timeout(10)->get('https://meditative-brains.com:3001/api/category');
            
            if ($response->successful()) {
                session()->flash('success', 'API connection successful! Found ' . count($response->json()) . ' categories.');
            } else {
                session()->flash('error', 'API connection failed. Status: ' . $response->status());
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Connection error: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.category-management')
            ->extends('adminlte::page')
            ->section('content');
    }
}
