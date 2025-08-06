<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use Livewire\WithPagination;
use App\Models\ProductCategory;
use Illuminate\Validation\Rule;

class CategoryManager extends AdminComponent
{
    use WithPagination;

    protected string $pageTitle = 'Category Manager';
    protected string $pageHeader = 'Category Manager';

    public $name = '';
    public $description = '';
    public $meta_title = '';
    public $meta_description = '';
    public $meta_keywords = '';
    public $is_active = true;
    public $sort_order = 0;
    public $parent_id = null;
    
    public $editingCategory = null;
    public $showForm = false;
    public $search = '';

    protected $paginationTheme = 'bootstrap';

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'parent_id' => 'nullable|exists:product_categories,id',
        ];
    }

    public function mount()
    {
        $this->resetForm();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->name = '';
        $this->description = '';
        $this->meta_title = '';
        $this->meta_description = '';
        $this->meta_keywords = '';
        $this->is_active = true;
        $this->sort_order = 0;
        $this->parent_id = null;
        $this->editingCategory = null;
        $this->showForm = false;
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($categoryId)
    {
        $category = ProductCategory::findOrFail($categoryId);
        $this->editingCategory = $category->id;
        $this->name = $category->name;
        $this->description = $category->description;
        $this->meta_title = $category->meta_title;
        $this->meta_description = $category->meta_description;
        $this->meta_keywords = $category->meta_keywords;
        $this->is_active = $category->is_active;
        $this->sort_order = $category->sort_order;
        $this->parent_id = $category->parent_id;
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->editingCategory) {
            $category = ProductCategory::findOrFail($this->editingCategory);
            $category->update([
                'name' => $this->name,
                'description' => $this->description,
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'meta_keywords' => $this->meta_keywords,
                'is_active' => $this->is_active,
                'sort_order' => $this->sort_order,
                'parent_id' => $this->parent_id,
            ]);
            session()->flash('message', 'Category updated successfully!');
        } else {
            ProductCategory::create([
                'name' => $this->name,
                'description' => $this->description,
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'meta_keywords' => $this->meta_keywords,
                'is_active' => $this->is_active,
                'sort_order' => $this->sort_order,
                'parent_id' => $this->parent_id,
            ]);
            session()->flash('message', 'Category created successfully!');
        }

        $this->resetForm();
    }

    public function delete($categoryId)
    {
        $category = ProductCategory::findOrFail($categoryId);
        $category->delete();
        session()->flash('message', 'Category deleted successfully!');
    }

    public function cancel()
    {
        $this->resetForm();
    }

    protected function getViewData(): array
    {
        $categories = ProductCategory::when($this->search, function ($query) {
                return $query->where('name', 'like', '%' . $this->search . '%')
                           ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);

        $parentCategories = ProductCategory::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return [
            'categories' => $categories,
            'parentCategories' => $parentCategories,
        ];
    }
}
