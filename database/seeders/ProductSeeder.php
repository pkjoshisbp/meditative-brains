<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $categories = ProductCategory::all();

        $products = [
            // TTS Affirmations
            [
                'name' => 'Daily Success Affirmations',
                'slug' => 'daily-success-affirmations',
                'description' => 'Powerful daily affirmations for success, confidence, and achievement. These TTS affirmations are designed to reprogram your subconscious mind for success.',
                'short_description' => 'Daily success affirmations for confidence and achievement',
                'price' => 9.99,
                'sale_price' => 7.99,
                'type' => 'digital',
                'audio_type' => 'tts_affirmation',
                'audio_features' => ['binaural'],
                'preview_duration' => 60,
                'tags' => 'success, confidence, daily affirmations, motivation',
                'meta_title' => 'Daily Success Affirmations - Meditative Brains',
                'meta_description' => 'Transform your mindset with powerful daily success affirmations designed for confidence and achievement.',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'category_id' => $categories->where('name', 'TTS Affirmations')->first()->id,
            ],
            [
                'name' => 'Self-Love & Confidence Booster',
                'slug' => 'self-love-confidence-booster',
                'description' => 'Nurture self-love and boost confidence with these gentle yet powerful affirmations.',
                'short_description' => 'Build self-love and confidence with targeted affirmations',
                'price' => 8.99,
                'type' => 'digital',
                'audio_type' => 'tts_affirmation',
                'audio_features' => ['solfeggio'],
                'preview_duration' => 45,
                'tags' => 'self-love, confidence, healing, personal growth',
                'is_active' => true,
                'category_id' => $categories->where('name', 'TTS Affirmations')->first()->id,
            ],

            // Sleep Aid Music
            [
                'name' => 'Deep Sleep Delta Waves',
                'slug' => 'deep-sleep-delta-waves',
                'description' => 'Scientifically designed delta wave frequencies to promote deep, restorative sleep. Perfect for insomnia relief.',
                'short_description' => 'Delta wave frequencies for deep, restorative sleep',
                'price' => 12.99,
                'sale_price' => 9.99,
                'type' => 'digital',
                'audio_type' => 'sleep_aid',
                'audio_features' => ['binaural', 'pink_noise'],
                'preview_duration' => 90,
                'tags' => 'sleep, delta waves, insomnia, deep sleep, relaxation',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'category_id' => $categories->where('name', 'Sleep Aid Music')->first()->id,
            ],
            [
                'name' => 'Rain & Thunder Sleep Sounds',
                'slug' => 'rain-thunder-sleep-sounds',
                'description' => 'Natural rain and gentle thunder sounds recorded in high quality for the perfect sleep ambiance.',
                'short_description' => 'Natural rain and thunder for peaceful sleep',
                'price' => 6.99,
                'type' => 'digital',
                'audio_type' => 'sleep_aid',
                'audio_features' => ['nature_sounds'],
                'preview_duration' => 60,
                'tags' => 'rain, thunder, nature sounds, sleep, relaxation',
                'is_active' => true,
                'category_id' => $categories->where('name', 'Sleep Aid Music')->first()->id,
            ],

            // Meditation Music
            [
                'name' => 'Tibetan Bowl Meditation',
                'slug' => 'tibetan-bowl-meditation',
                'description' => 'Authentic Tibetan singing bowls recorded in pristine quality for deep meditation and mindfulness practice.',
                'short_description' => 'Authentic Tibetan singing bowls for meditation',
                'price' => 14.99,
                'type' => 'digital',
                'audio_type' => 'meditation',
                'audio_features' => ['solfeggio'],
                'preview_duration' => 120,
                'tags' => 'tibetan bowls, meditation, mindfulness, spiritual',
                'is_active' => true,
                'is_featured' => true,
                'category_id' => $categories->where('name', 'Meditation Music')->first()->id,
            ],

            // Binaural Beats
            [
                'name' => 'Focus & Concentration Alpha Waves',
                'slug' => 'focus-concentration-alpha-waves',
                'description' => 'Alpha wave binaural beats specifically tuned for enhanced focus, concentration, and mental clarity.',
                'short_description' => 'Alpha waves for enhanced focus and concentration',
                'price' => 11.99,
                'type' => 'digital',
                'audio_type' => 'binaural_beats',
                'audio_features' => ['binaural', 'isochronic'],
                'preview_duration' => 75,
                'tags' => 'focus, concentration, alpha waves, productivity, study',
                'is_active' => true,
                'category_id' => $categories->where('name', 'Binaural Beats')->first()->id,
            ],

            // Nature Sounds
            [
                'name' => 'Ocean Waves & Seagulls',
                'slug' => 'ocean-waves-seagulls',
                'description' => 'Calming ocean waves with distant seagull calls, perfect for relaxation and stress relief.',
                'short_description' => 'Calming ocean sounds for relaxation',
                'price' => 5.99,
                'type' => 'digital',
                'audio_type' => 'nature_sounds',
                'audio_features' => ['nature_sounds'],
                'preview_duration' => 60,
                'tags' => 'ocean, waves, seagulls, relaxation, stress relief',
                'is_active' => true,
                'category_id' => $categories->where('name', 'Nature Sounds')->first()->id,
            ],

            // Solfeggio Frequencies
            [
                'name' => '528Hz Love Frequency',
                'slug' => '528hz-love-frequency',
                'description' => 'The 528Hz "Love Frequency" is said to resonate at the heart of everything, promoting healing and love.',
                'short_description' => 'The healing 528Hz love frequency',
                'price' => 13.99,
                'type' => 'digital',
                'audio_type' => 'solfeggio',
                'audio_features' => ['solfeggio'],
                'preview_duration' => 90,
                'tags' => '528hz, love frequency, healing, chakra, spiritual',
                'is_active' => true,
                'is_featured' => true,
                'category_id' => $categories->where('name', 'Solfeggio Frequencies')->first()->id,
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }
    }
}
