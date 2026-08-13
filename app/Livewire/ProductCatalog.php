<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductCatalog extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryId = '';
    public $sortBy = 'featured';
    public $audioFeatures = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryId' => ['except' => ''],
        'sortBy' => ['except' => 'featured'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategoryId()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset([
            'search',
            'categoryId',
            'audioFeatures',
            'sortBy'
        ]);
    }

    public function addToCart($productId)
    {
        $product = Product::findOrFail($productId);
        
        if (auth()->check()) {
            // Add to user's cart
            auth()->user()->cartItems()->updateOrCreate(
                ['product_id' => $productId],
                [
                    'quantity' => 1,
                    'price' => $product->getCurrentPrice(),
                ]
            );
            $count = auth()->user()->cartItems()->count();
        } else {
            // Add to session cart
            $cart = session()->get('cart', []);
            $cart[$productId] = [
                'name' => $product->name,
                'price' => $product->getCurrentPrice(),
                'quantity' => 1,
            ];
            session()->put('cart', $cart);
            $count = count($cart);
        }

        $this->dispatch('cart-updated', count: $count);
        session()->flash('message', "\"{$product->name}\" added to cart!");
    }

    public function render()
    {
        $query = Product::with(['category', 'media'])
            ->where('is_active', true);

        // Apply search filter
        if ($this->search) {
            $query->search($this->search);
        }

        // Apply category filter
        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        // Apply audio features filter
        if (!empty($this->audioFeatures)) {
            $query->where(function ($q) {
                foreach ($this->audioFeatures as $feature) {
                    $q->orWhereJsonContains('audio_features', $feature);
                }
            });
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'popular':
                $query->orderBy('downloads', 'desc');
                break;
            case 'featured':
            default:
                $query->orderBy('is_featured', 'desc')
                      ->orderBy('sort_order', 'asc')
                      ->orderBy('created_at', 'desc');
                break;
        }

        $products = $query->paginate(12);

        $categories = ProductCategory::where('is_active', true)
            ->withCount('activeProducts')
            ->having('active_products_count', '>', 0)
            ->orderBy('sort_order')
            ->get();

        $audioFeatureOptions = [
            'solfeggio' => 'Solfeggio Frequencies',
            'isochronic' => 'Isochronic Tones',
            'monoaural' => 'Monoaural Beats',
            'pink_noise' => 'Pink Noise',
            'nature_sounds' => 'Nature Sounds',
            'binaural' => 'Binaural Beats',
        ];

        return view('livewire.product-catalog', [
            'products' => $products,
            'categories' => $categories,
            'audioFeatureOptions' => $audioFeatureOptions,
        ])->layout('layouts.app-frontend', [
            'title' => 'Mental Fitness Store Catalog - Audio, Ebooks & Audiobooks',
            'description' => 'Browse mental wellness audio, Practicing Happiness ebook and audiobook bundles, affirmations, sleep music, meditation tracks, binaural beats, nature sounds, and solfeggio frequencies.',
            'keywords' => 'mental wellness audio catalog, practicing happiness ebook, happiness audiobook, affirmations, sleep music, meditation tracks, binaural beats, solfeggio frequencies, nature sounds'
        ]);
    }
}
