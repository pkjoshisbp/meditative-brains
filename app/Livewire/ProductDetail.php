<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;

class ProductDetail extends Component
{
    public Product $product;

    public function mount(string $slug): void
    {
        $this->product = Product::with(['category', 'media', 'linkedAudiobook', 'relatedAudioProduct.category'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function addToCart(): void
    {
        if (auth()->check()) {
            auth()->user()->cartItems()->updateOrCreate(
                ['product_id' => $this->product->id],
                [
                    'quantity' => 1,
                    'price' => $this->product->getCurrentPrice(),
                ]
            );

            $count = auth()->user()->cartItems()->count();
        } else {
            $cart = session()->get('cart', []);
            $cart[$this->product->id] = [
                'name' => $this->product->name,
                'price' => $this->product->getCurrentPrice(),
                'quantity' => 1,
            ];
            session()->put('cart', $cart);
            $count = count($cart);
        }

        $this->dispatch('cart-updated', count: $count);
        session()->flash('message', '"' . $this->product->name . '" added to cart!');
    }

    public function render()
    {
        $similarProducts = Product::with(['category', 'media'])
            ->where('is_active', true)
            ->where('id', '!=', $this->product->id)
            ->where('category_id', $this->product->category_id)
            ->when($this->product->related_audio_product_id, fn ($query) => $query->where('id', '!=', $this->product->related_audio_product_id))
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        return view('livewire.product-detail', [
            'product' => $this->product,
            'similarProducts' => $similarProducts,
        ])->layout('layouts.app-frontend', [
            'title' => $this->product->meta_title ?: $this->product->name,
            'description' => $this->product->meta_description ?: ($this->product->short_description ?: $this->product->description ?: 'Premium audio experience'),
            'keywords' => $this->product->meta_keywords ?: 'audio product, audiobook, wellness audio',
        ]);
    }
}
