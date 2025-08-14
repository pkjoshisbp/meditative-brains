<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TtsAudioProduct;

class BackgroundMusicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update TTS products with actual background music URLs
        $backgroundMusicUrls = [
            'self_confidence' => 'https://meditative-brains.com:3001/background-music/confidence-empowerment.mp3',
            'success_mindset' => 'https://meditative-brains.com:3001/background-music/success-achievement.mp3',
            'health_wellness' => 'https://meditative-brains.com:3001/background-music/wellness-vitality.mp3',
            'relationships' => 'https://meditative-brains.com:3001/background-music/love-harmony.mp3',
            'financial_abundance' => 'https://meditative-brains.com:3001/background-music/prosperity-wealth.mp3'
        ];

        $coverImages = [
            'self_confidence' => 'https://meditative-brains.com/images/covers/confidence-cover.jpg',
            'success_mindset' => 'https://meditative-brains.com/images/covers/success-cover.jpg',
            'health_wellness' => 'https://meditative-brains.com/images/covers/health-cover.jpg',
            'relationships' => 'https://meditative-brains.com/images/covers/relationships-cover.jpg',
            'financial_abundance' => 'https://meditative-brains.com/images/covers/wealth-cover.jpg'
        ];

        foreach ($backgroundMusicUrls as $category => $musicUrl) {
            TtsAudioProduct::where('category', $category)->update([
                'background_music_url' => $musicUrl,
                'cover_image_url' => $coverImages[$category] ?? null
            ]);
            
            $this->command->info("Updated background music for category: {$category}");
        }

        // Add some additional enhanced sample messages
        $enhancedSamples = [
            'self_confidence' => [
                "I radiate confidence and self-assurance in everything I do",
                "My inner strength grows more powerful with each passing day",
                "I trust my intuition and make decisions with complete confidence",
                "My self-worth is unshakeable and comes from deep within",
                "I speak my truth with clarity, courage, and conviction"
            ],
            'success_mindset' => [
                "I am destined for greatness and success flows naturally to me",
                "Every challenge I face makes me stronger and more resilient",
                "I have the mindset of a champion and the heart of a winner",
                "Success is my natural state and I embrace it fully",
                "I think like a millionaire and act with unstoppable determination"
            ],
            'health_wellness' => [
                "My body is a temple of health, vitality, and perfect wellness",
                "Every cell in my body vibrates with pure life force energy",
                "I make choices that honor and nourish my body completely",
                "My immune system is strong and protects me perfectly",
                "I am grateful for my healthy, strong, and vibrant body"
            ],
            'relationships' => [
                "I attract loving, supportive relationships that enrich my life",
                "Love flows through me and touches everyone I encounter",
                "I communicate with compassion, understanding, and deep love",
                "My heart is open to giving and receiving unconditional love",
                "I am surrounded by people who appreciate and value me"
            ],
            'financial_abundance' => [
                "Money flows to me effortlessly from multiple sources",
                "I am worthy of unlimited financial abundance and prosperity",
                "Every financial decision I make increases my wealth exponentially",
                "I attract lucrative opportunities that align with my values",
                "My wealth creates positive impact for myself and others"
            ]
        ];

        foreach ($enhancedSamples as $category => $samples) {
            TtsAudioProduct::where('category', $category)->update([
                'sample_messages' => $samples,
                'total_messages_count' => rand(120, 200) // Randomize message counts
            ]);
        }

        $this->command->info('Enhanced TTS products with background music and improved samples!');
        $this->command->info('Updated ' . count($backgroundMusicUrls) . ' products with background music URLs.');
    }
}
