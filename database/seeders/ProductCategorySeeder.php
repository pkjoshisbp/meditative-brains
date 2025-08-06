<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'TTS Affirmations',
                'description' => 'Text-to-speech affirmations for personal development and motivation',
                'meta_title' => 'TTS Affirmations - Meditative Brains',
                'meta_description' => 'Powerful text-to-speech affirmations to transform your mindset and boost motivation.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Sleep Aid Music',
                'description' => 'Specially designed music to help you fall asleep and improve sleep quality',
                'meta_title' => 'Sleep Aid Music - Meditative Brains',
                'meta_description' => 'Relaxing sleep music with binaural beats and soothing sounds for better sleep.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Meditation Music',
                'description' => 'Peaceful music for meditation and mindfulness practices',
                'meta_title' => 'Meditation Music - Meditative Brains',
                'meta_description' => 'Ambient meditation music to enhance your mindfulness and spiritual practice.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Binaural Beats',
                'description' => 'Scientifically designed binaural beats for various mental states',
                'meta_title' => 'Binaural Beats - Meditative Brains',
                'meta_description' => 'Therapeutic binaural beats for focus, relaxation, and consciousness enhancement.',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Nature Sounds',
                'description' => 'High-quality nature sounds for relaxation and focus',
                'meta_title' => 'Nature Sounds - Meditative Brains',
                'meta_description' => 'Immersive nature soundscapes including rain, ocean waves, and forest sounds.',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Solfeggio Frequencies',
                'description' => 'Ancient sound frequencies for healing and spiritual growth',
                'meta_title' => 'Solfeggio Frequencies - Meditative Brains',
                'meta_description' => 'Sacred solfeggio frequencies for chakra healing and spiritual awakening.',
                'is_active' => true,
                'sort_order' => 6,
            ]
        ];

        foreach ($categories as $category) {
            ProductCategory::create($category);
        }
    }
}
