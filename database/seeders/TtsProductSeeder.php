<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TtsCategory;
use App\Models\TtsAudioProduct;

class TtsProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create TTS Categories
        $categories = [
            [
                'name' => 'self_confidence',
                'display_name' => 'Self Confidence',
                'description' => 'Boost your self-confidence with powerful motivational messages',
                'icon_url' => 'https://example.com/icons/confidence.png',
                'sort_order' => 1
            ],
            [
                'name' => 'success_mindset',
                'display_name' => 'Success Mindset',
                'description' => 'Develop a winning mindset for success in all areas of life',
                'icon_url' => 'https://example.com/icons/success.png',
                'sort_order' => 2
            ],
            [
                'name' => 'health_wellness',
                'display_name' => 'Health & Wellness',
                'description' => 'Motivational messages for health, fitness, and wellness goals',
                'icon_url' => 'https://example.com/icons/health.png',
                'sort_order' => 3
            ],
            [
                'name' => 'relationships',
                'display_name' => 'Relationships',
                'description' => 'Improve your relationships and social connections',
                'icon_url' => 'https://example.com/icons/relationships.png',
                'sort_order' => 4
            ],
            [
                'name' => 'financial_abundance',
                'display_name' => 'Financial Abundance',
                'description' => 'Attract wealth and financial prosperity',
                'icon_url' => 'https://example.com/icons/money.png',
                'sort_order' => 5
            ]
        ];

        foreach ($categories as $categoryData) {
            TtsCategory::create($categoryData);
        }

        // Create TTS Audio Products
        $products = [
            // Self Confidence Products
            [
                'name' => 'Unshakeable Self Confidence',
                'description' => 'Build rock-solid confidence that cannot be shaken by external circumstances',
                'category' => 'self_confidence',
                'language' => 'en',
                'price' => 4.99,
                'preview_duration' => 30,
                'background_music_url' => 'https://example.com/bg-music/confidence-bg.mp3',
                'cover_image_url' => 'https://example.com/covers/confidence-cover.jpg',
                'sample_messages' => [
                    "I am confident in my abilities and trust my decisions",
                    "My self-worth comes from within and grows stronger each day",
                    "I speak with confidence and clarity in all situations",
                    "I believe in myself and my capacity to achieve great things",
                    "My confidence radiates outward and inspires others"
                ],
                'total_messages_count' => 150
            ],
            
            // Success Mindset Products
            [
                'name' => 'Millionaire Success Mindset',
                'description' => 'Develop the mindset of successful entrepreneurs and high achievers',
                'category' => 'success_mindset',
                'language' => 'en',
                'price' => 6.99,
                'preview_duration' => 30,
                'background_music_url' => 'https://example.com/bg-music/success-bg.mp3',
                'cover_image_url' => 'https://example.com/covers/success-cover.jpg',
                'sample_messages' => [
                    "I have the mindset of a successful entrepreneur",
                    "Every challenge is an opportunity for growth and success",
                    "I attract abundance and prosperity in all my endeavors",
                    "My actions are aligned with my vision of success",
                    "I persist through obstacles and emerge victorious"
                ],
                'total_messages_count' => 200
            ],

            // Health & Wellness Products
            [
                'name' => 'Vibrant Health & Energy',
                'description' => 'Motivational messages for optimal health, fitness, and vitality',
                'category' => 'health_wellness',
                'language' => 'en',
                'price' => 4.99,
                'preview_duration' => 30,
                'background_music_url' => 'https://example.com/bg-music/health-bg.mp3',
                'cover_image_url' => 'https://example.com/covers/health-cover.jpg',
                'sample_messages' => [
                    "My body is strong, healthy, and full of vitality",
                    "I make choices that nourish and energize my body",
                    "Every cell in my body radiates perfect health",
                    "I am committed to my wellness journey",
                    "My energy levels increase with each healthy choice I make"
                ],
                'total_messages_count' => 120
            ],

            // Relationships Products
            [
                'name' => 'Loving Relationships & Connection',
                'description' => 'Attract and maintain meaningful, loving relationships',
                'category' => 'relationships',
                'language' => 'en',
                'price' => 5.99,
                'preview_duration' => 30,
                'background_music_url' => 'https://example.com/bg-music/love-bg.mp3',
                'cover_image_url' => 'https://example.com/covers/relationships-cover.jpg',
                'sample_messages' => [
                    "I attract loving and supportive relationships into my life",
                    "I communicate with love, compassion, and understanding",
                    "My relationships are built on trust, respect, and mutual growth",
                    "I am worthy of deep, meaningful connections",
                    "Love flows through me and touches everyone I meet"
                ],
                'total_messages_count' => 100
            ],

            // Financial Abundance Products
            [
                'name' => 'Wealth Magnet Affirmations',
                'description' => 'Attract financial abundance and prosperity into your life',
                'category' => 'financial_abundance',
                'language' => 'en',
                'price' => 7.99,
                'preview_duration' => 30,
                'background_music_url' => 'https://example.com/bg-music/wealth-bg.mp3',
                'cover_image_url' => 'https://example.com/covers/wealth-cover.jpg',
                'sample_messages' => [
                    "I am a magnet for financial abundance and prosperity",
                    "Money flows to me easily and effortlessly",
                    "I make wise financial decisions that multiply my wealth",
                    "Opportunities for financial growth surround me",
                    "I deserve and accept unlimited financial abundance"
                ],
                'total_messages_count' => 180
            ]
        ];

        foreach ($products as $productData) {
            TtsAudioProduct::create($productData);
        }

        $this->command->info('TTS Products seeded successfully!');
        $this->command->info('Created ' . count($categories) . ' categories and ' . count($products) . ' products.');
    }
}
