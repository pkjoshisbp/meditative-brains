<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\TtsAudioProduct;

class AudioExperienceDetail extends Component
{
    public TtsAudioProduct $product;

    public function mount($slug)
    {
        $this->product = TtsAudioProduct::active()->where('slug', $slug)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.audio-experience-detail', [
            'product' => $this->product,
        ])->layout('layouts.app-frontend', [
            'title' => $this->product->meta_title ?: $this->product->name,
            'description' => $this->product->meta_description ?: ($this->product->short_description ?? 'Meditative Minds audio experience'),
            'keywords' => $this->product->meta_keywords ?? ''
        ]);
    }
}
