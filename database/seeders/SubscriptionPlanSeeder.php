<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Music Library Monthly',
                'slug' => 'music-library-monthly',
                'description' => 'Access to our complete music library including meditation music, binaural beats, nature sounds, and solfeggio frequencies.',
                'price' => 9.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    'Complete Music Library Access',
                    'High-Quality Audio Downloads',
                    'Unlimited Streaming',
                    'New Music Added Monthly',
                    'Mobile App Access'
                ],
                'access_rules' => [
                    'music_library' => 'full_access'
                ],
                'includes_music_library' => true,
                'includes_all_tts_categories' => false,
                'included_tts_categories' => [],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 7,
                'sort_order' => 1
            ],
            [
                'name' => 'TTS Affirmations Complete',
                'slug' => 'tts-affirmations-complete',
                'description' => 'Access to all TTS affirmation categories for personal development and motivation.',
                'price' => 14.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    'All TTS Affirmation Categories',
                    'Self Confidence Affirmations',
                    'Positive Attitude Training',
                    'Quit Smoking Support',
                    'Will Power Enhancement',
                    'Guided Visualization',
                    'Hypnotherapy Sessions'
                ],
                'access_rules' => [
                    'tts_categories' => 'all'
                ],
                'includes_music_library' => false,
                'includes_all_tts_categories' => true,
                'included_tts_categories' => [],
                'is_active' => true,
                'is_featured' => true,
                'trial_days' => 3,
                'sort_order' => 2
            ],
            [
                'name' => 'Premium All Access',
                'slug' => 'premium-all-access',
                'description' => 'Complete access to both music library and all TTS affirmation categories.',
                'price' => 19.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    'Complete Music Library',
                    'All TTS Affirmation Categories',
                    'Priority Customer Support',
                    'Early Access to New Content',
                    'High-Quality Downloads',
                    'Mobile App Access',
                    'Offline Listening'
                ],
                'access_rules' => [
                    'music_library' => 'full_access',
                    'tts_categories' => 'all'
                ],
                'includes_music_library' => true,
                'includes_all_tts_categories' => true,
                'included_tts_categories' => [],
                'is_active' => true,
                'is_featured' => true,
                'trial_days' => 7,
                'sort_order' => 3
            ],
            [
                'name' => 'Music Library Yearly',
                'slug' => 'music-library-yearly',
                'description' => 'Annual access to our complete music library with significant savings.',
                'price' => 89.99,
                'billing_cycle' => 'yearly',
                'features' => [
                    'Complete Music Library Access',
                    'High-Quality Audio Downloads',
                    'Unlimited Streaming',
                    'New Music Added Monthly',
                    'Mobile App Access',
                    '25% Savings vs Monthly'
                ],
                'access_rules' => [
                    'music_library' => 'full_access'
                ],
                'includes_music_library' => true,
                'includes_all_tts_categories' => false,
                'included_tts_categories' => [],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 14,
                'sort_order' => 4
            ],
            [
                'name' => 'Premium All Access Yearly',
                'slug' => 'premium-all-access-yearly',
                'description' => 'Annual premium access with everything included and maximum savings.',
                'price' => 199.99,
                'billing_cycle' => 'yearly',
                'features' => [
                    'Complete Music Library',
                    'All TTS Affirmation Categories',
                    'Priority Customer Support',
                    'Early Access to New Content',
                    'High-Quality Downloads',
                    'Mobile App Access',
                    'Offline Listening',
                    '50% Savings vs Monthly'
                ],
                'access_rules' => [
                    'music_library' => 'full_access',
                    'tts_categories' => 'all'
                ],
                'includes_music_library' => true,
                'includes_all_tts_categories' => true,
                'included_tts_categories' => [],
                'is_active' => true,
                'is_featured' => true,
                'trial_days' => 14,
                'sort_order' => 5
            ],
            [
                'name' => 'Self Confidence Package',
                'slug' => 'self-confidence-package',
                'description' => 'Focused package for building self-confidence with targeted TTS affirmations.',
                'price' => 7.99,
                'billing_cycle' => 'monthly',
                'features' => [
                    'Self Confidence Affirmations',
                    'Positive Attitude Training',
                    'Will Power Enhancement',
                    'Mobile App Access'
                ],
                'access_rules' => [
                    'tts_categories' => 'selected'
                ],
                'includes_music_library' => false,
                'includes_all_tts_categories' => false,
                'included_tts_categories' => [
                    'Self Confidence',
                    'Positive Attitude',
                    'Will Power'
                ],
                'is_active' => true,
                'is_featured' => false,
                'trial_days' => 3,
                'sort_order' => 6
            ]
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
