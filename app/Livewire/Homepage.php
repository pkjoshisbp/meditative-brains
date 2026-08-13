<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;
use App\Models\ProductCategory;

class Homepage extends Component
{
    public function render()
    {
        $featuredProducts = Product::with(['category', 'media'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        $categories = ProductCategory::where('is_active', true)
            ->withCount('activeProducts')
            ->having('active_products_count', '>', 0)
            ->orderBy('sort_order')
            ->limit(6)
            ->get();

        $newProducts = Product::with(['category', 'media'])
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get();

        return view('livewire.homepage', [
            'featuredProducts' => $featuredProducts,
            'categories' => $categories,
            'newProducts' => $newProducts,
        ])->layout('layouts.app-frontend', [
            'title' => 'Mental Fitness Store - Wellness Audio, Ebooks & Audiobooks',
            'description' => 'Train your mind with premium TTS affirmations, sleep music, meditation tracks, binaural beats, solfeggio frequencies, nature sounds, and the Practicing Happiness ebook and audiobook.',
            'keywords' => 'mental fitness, TTS affirmations, sleep music, meditation, binaural beats, solfeggio frequencies, nature sounds, practicing happiness ebook, audiobook, emotional resilience, mindfulness, personal development'
        ]);
    }
}
