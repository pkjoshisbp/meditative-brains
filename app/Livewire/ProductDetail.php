<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;

class ProductDetail extends Component
{
    public Product $product;

    public function mount(string $slug): void
    {
        $this->product = Product::with(['category', 'media', 'linkedAudiobook'])
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
        return view('livewire.product-detail', [
            'product' => $this->product,
        ])->layout('layouts.app-frontend', [
            'title' => $this->product->meta_title ?: $this->product->name,
            'description' => $this->product->meta_description ?: ($this->product->short_description ?: $this->product->description ?: 'Premium audio experience'),
            'keywords' => $this->product->meta_keywords ?: 'audio product, audiobook, wellness audio',
        ]);
    }
}