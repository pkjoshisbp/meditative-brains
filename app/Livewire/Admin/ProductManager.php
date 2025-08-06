<?php

namespace App\Livewire\Admin;

use App\Livewire\AdminComponent;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AudioSecurityService;
use Illuminate\Support\Facades\Storage;

class ProductManager extends AdminComponent
{
    use WithPagination, WithFileUploads;
    
    protected string $pageTitle = 'Product Manager';
    protected string $pageHeader = 'Product Manager';

    public $name = '';
    public $description = '';
    public $short_description = '';
    public $price = '';
    public $sale_price = '';
    public $type = 'digital';
    public $audio_type = '';
    public $audio_features = [];
    public $preview_duration = 30;
    public $tags = '';
    public $meta_title = '';
    public $meta_description = '';
    public $meta_keywords = '';
    public $is_active = true;
    public $is_featured = false;
    public $sort_order = 0;
    public $category_id = '';
    
    public $preview_file;
    public $full_file;
    
    // File browser integration
    public $selectedOriginalFile = '';
    public $showFileBrowser = false;
    
    public $editingProduct = null;
    public $showForm = false;
    public $search = '';
    public $filterCategory = '';

    protected $paginationTheme = 'bootstrap';

    protected $audioFeatureOptions = [
        'solfeggio' => 'Solfeggio Frequencies',
        'isochronic' => 'Isochronic Tones',
        'monoaural' => 'Monoaural Beats',
        'pink_noise' => 'Pink Noise',
        'nature_sounds' => 'Nature Sounds',
        'binaural' => 'Binaural Beats',
    ];

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'type' => 'required|in:digital,physical',
            'audio_type' => 'nullable|string',
            'preview_duration' => 'required|integer|min:10|max:120',
            'tags' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer|min:0',
            'category_id' => 'required|exists:product_categories,id',
            'preview_file' => 'nullable|file|mimes:mp3,wav,ogg|max:10240',
            'full_file' => 'nullable|file|mimes:mp3,wav,ogg|max:51200',
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

    public function updatingFilterCategory()
    {
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->name = '';
        $this->description = '';
        $this->short_description = '';
        $this->price = '';
        $this->sale_price = '';
        $this->type = 'digital';
        $this->audio_type = '';
        $this->audio_features = [];
        $this->preview_duration = 30;
        $this->tags = '';
        $this->meta_title = '';
        $this->meta_description = '';
        $this->meta_keywords = '';
        $this->is_active = true;
        $this->is_featured = false;
        $this->sort_order = 0;
        $this->category_id = '';
        $this->selectedOriginalFile = '';
        $this->preview_file = null;
        $this->full_file = null;
        $this->editingProduct = null;
        $this->showForm = false;
        $this->showFileBrowser = false;
    }

    #[On('file-selected')]
    public function handleFileSelection($filePath)
    {
        $this->selectedOriginalFile = $filePath;
        $this->showFileBrowser = false;
        session()->flash('message', 'File selected: ' . basename($filePath));
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($productId)
    {
        $product = Product::with('category')->findOrFail($productId);
        $this->editingProduct = $product->id;
        $this->name = $product->name;
        $this->description = $product->description;
        $this->short_description = $product->short_description;
        $this->price = $product->price;
        $this->sale_price = $product->sale_price;
        $this->type = $product->type;
        $this->audio_type = $product->audio_type;
        $this->audio_features = $product->audio_features ?? [];
        $this->preview_duration = $product->preview_duration;
        $this->tags = $product->tags;
        $this->meta_title = $product->meta_title;
        $this->meta_description = $product->meta_description;
        $this->meta_keywords = $product->meta_keywords;
        $this->is_active = $product->is_active;
        $this->is_featured = $product->is_featured;
        $this->sort_order = $product->sort_order;
        $this->category_id = $product->category_id;
        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'type' => $this->type,
            'audio_type' => $this->audio_type,
            'audio_features' => $this->audio_features,
            'preview_duration' => $this->preview_duration,
            'tags' => $this->tags,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'category_id' => $this->category_id,
        ];

        $audioService = app(AudioSecurityService::class);

        // Handle file uploads with encryption
        if ($this->preview_file) {
            // Store preview file normally for public access
            $data['preview_file'] = $this->preview_file->store('products/previews', 'public');
        }

        // Handle selected original file encryption
        if ($this->selectedOriginalFile) {
            try {
                // Create a temporary product ID for encryption if creating new product
                $tempProductId = $this->editingProduct ?: time();
                $encryptedPath = $audioService->encryptOriginalFile($this->selectedOriginalFile, $tempProductId);
                $data['audio_path'] = $encryptedPath;
            } catch (\Exception $e) {
                session()->flash('error', 'Failed to encrypt selected audio file: ' . $e->getMessage());
                return;
            }
        }

        // Legacy file upload handling (keep for backward compatibility)
        if ($this->full_file) {
            // Encrypt and store full audio file
            $tempPath = $this->full_file->storeAs('temp', uniqid() . '.' . $this->full_file->getClientOriginalExtension());
            $fullPath = storage_path('app/' . $tempPath);
            
            try {
                $encryptedPath = $audioService->encryptAndStore($fullPath);
                $data['audio_path'] = $encryptedPath;
                
                // Clean up temp file
                Storage::delete($tempPath);
            } catch (\Exception $e) {
                Storage::delete($tempPath);
                session()->flash('error', 'Failed to encrypt audio file: ' . $e->getMessage());
                return;
            }
        }

        if ($this->editingProduct) {
            $product = Product::findOrFail($this->editingProduct);
            
            // Delete old files if new ones are uploaded
            if ($this->preview_file && $product->preview_file) {
                Storage::disk('public')->delete($product->preview_file);
            }
            if (($this->selectedOriginalFile || $this->full_file) && $product->audio_path) {
                Storage::disk('local')->delete($product->audio_path);
            }
            
            $product->update($data);
            session()->flash('message', 'Product updated successfully!');
        } else {
            Product::create($data);
            session()->flash('message', 'Product created successfully!');
        }

        $this->resetForm();
    }

    public function delete($productId)
    {
        $product = Product::findOrFail($productId);
        
        // Delete associated files
        if ($product->preview_file) {
            Storage::disk('public')->delete($product->preview_file);
        }
        if ($product->audio_path) {
            Storage::disk('local')->delete($product->audio_path);
        }
        
        $product->delete();
        session()->flash('message', 'Product deleted successfully!');
    }

    public function cancel()
    {
        $this->resetForm();
    }

    protected function getViewData(): array
    {
        $products = Product::with('category')
            ->when($this->search, function ($query) {
                return $query->where('name', 'like', '%' . $this->search . '%')
                           ->orWhere('description', 'like', '%' . $this->search . '%')
                           ->orWhere('tags', 'like', '%' . $this->search . '%');
            })
            ->when($this->filterCategory, function ($query) {
                return $query->where('category_id', $this->filterCategory);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);

        $categories = ProductCategory::where('is_active', true)
            ->orderBy('name')
            ->get();

        return [
            'products' => $products,
            'categories' => $categories,
            'audioFeatureOptions' => $this->audioFeatureOptions,
        ];
    }
}
