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
            'title' => 'Meditative Brains - Premium TTS Affirmations & Sleep Aid Music',
            'description' => 'Transform your life with our premium collection of TTS affirmations, sleep aid music, meditation tracks, binaural beats, and healing frequencies. Start your wellness journey today.',
            'keywords' => 'TTS affirmations, sleep music, meditation, binaural beats, solfeggio frequencies, nature sounds, wellness, mindfulness, personal development'
        ]);
    }
}
