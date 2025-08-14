<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TtsCategory;
use App\Models\TtsAudioProduct;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TtsBackendController extends Controller
{
    protected $accessControlService;
    protected $ttsBackendUrl;

    public function __construct(AccessControlService $accessControlService)
    {
        $this->accessControlService = $accessControlService;
        $this->ttsBackendUrl = 'https://meditative-brains.com:3001';
    }

    /**
     * Get motivation messages for specific category with access control
     */
    public function getCategoryMessages(Request $request, $category)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        // Check if user has access to this category (subscription or product purchase)
        $accessCheck = $user->hasTtsCategoryAccessExtended($category);
        
        if (!$accessCheck['has_access']) {
            // Get available products for this category
            $availableProducts = TtsAudioProduct::byCategory($category)
                ->active()
                ->select('id', 'name', 'price', 'description')
                ->get();

            return response()->json([
                'error' => 'Access denied',
                'reason' => 'No subscription or product purchase found for this category',
                'category' => $category,
                'access_type' => $accessCheck['access_type'],
                'available_for_purchase' => true,
                'available_products' => $availableProducts
            ], 403);
        }

        try {
            // Fetch messages from TTS backend
            $response = Http::timeout(30)->get($this->ttsBackendUrl . '/api/motivationMessage/category/' . urlencode($category));
            
            if ($response->successful()) {
                $messages = $response->json();
                
                // Add access information to response
                return response()->json([
                    'success' => true,
                    'category' => $category,
                    'access_info' => $accessCheck,
                    'messages' => $messages,
                    'total_count' => count($messages)
                ]);
            }

            return response()->json([
                'error' => 'Failed to fetch messages from TTS backend'
            ], 500);

        } catch (\Exception $e) {
            Log::error('TTS Backend Error', [
                'error' => $e->getMessage(),
                'category' => $category,
                'user_id' => $user->id
            ]);

            return response()->json([
                'error' => 'TTS service temporarily unavailable'
            ], 503);
        }
    }

    /**
     * Generate audio for a specific message (requires access)
     */
    public function generateAudio(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $request->validate([
            'message_id' => 'required|string',
            'category' => 'required|string',
            'voice' => 'sometimes|string',
            'speed' => 'sometimes|numeric|min:0.5|max:2.0'
        ]);

        // Check if user has access to this category
        $accessCheck = $this->accessControlService->canUserAccess($user, 'tts_category', $request->category);
        
        if (!$accessCheck['can_access']) {
            return response()->json([
                'error' => 'Access denied',
                'reason' => $accessCheck['reason'],
                'category' => $request->category
            ], 403);
        }

        try {
            // Generate audio via TTS backend
            $response = Http::timeout(60)->post($this->ttsBackendUrl . '/api/motivationMessage/generate-audio', [
                'messageId' => $request->message_id,
                'voice' => $request->voice ?? 'en-US-AriaNeural',
                'speed' => $request->speed ?? 1.0
            ]);
            
            if ($response->successful()) {
                $result = $response->json();
                
                return response()->json([
                    'success' => true,
                    'message_id' => $request->message_id,
                    'audio_url' => $result['audioUrl'] ?? null,
                    'duration' => $result['duration'] ?? null,
                    'voice_used' => $request->voice ?? 'en-US-AriaNeural'
                ]);
            }

            return response()->json([
                'error' => 'Failed to generate audio'
            ], 500);

        } catch (\Exception $e) {
            Log::error('TTS Audio Generation Error', [
                'error' => $e->getMessage(),
                'message_id' => $request->message_id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'error' => 'Audio generation temporarily unavailable'
            ], 503);
        }
    }

    /**
     * Get all available voices for TTS
     */
    public function getAvailableVoices(Request $request)
    {
        try {
            $response = Http::timeout(10)->get($this->ttsBackendUrl . '/api/voices');
            
            if ($response->successful()) {
                return response()->json($response->json());
            }

            // Fallback to default voices if backend is unavailable
            return response()->json([
                'voices' => [
                    [
                        'name' => 'en-US-AriaNeural',
                        'language' => 'English (US)',
                        'gender' => 'Female',
                        'provider' => 'Azure'
                    ],
                    [
                        'name' => 'en-US-JennyNeural',
                        'language' => 'English (US)',
                        'gender' => 'Female',
                        'provider' => 'Azure'
                    ],
                    [
                        'name' => 'hi-IN-SwaraNeural',
                        'language' => 'Hindi (India)',
                        'gender' => 'Female',
                        'provider' => 'Azure'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('TTS Voices Fetch Error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'error' => 'Unable to fetch voices'
            ], 503);
        }
    }

    /**
     * Get user's TTS usage statistics
     */
    public function getUserStats(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $accessibleCategories = $user->getAccessibleTtsCategories();
        $allCategories = $this->accessControlService->getAllTtsCategories();

        return response()->json([
            'user_id' => $user->id,
            'accessible_categories' => $accessibleCategories,
            'total_accessible' => count($accessibleCategories),
            'total_available' => count($allCategories),
            'access_percentage' => count($allCategories) > 0 ? round((count($accessibleCategories) / count($allCategories)) * 100, 1) : 0,
            'subscription_info' => $user->getActiveSubscription(),
            'has_full_access' => count($accessibleCategories) === count($allCategories)
        ]);
    }

    /**
     * Search messages across accessible categories
     */
    public function searchMessages(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $request->validate([
            'query' => 'required|string|min:3|max:100',
            'categories' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        $accessibleCategories = $user->getAccessibleTtsCategories();
        
        if (empty($accessibleCategories)) {
            return response()->json([
                'error' => 'No accessible categories. Consider purchasing a subscription.',
                'results' => [],
                'total_count' => 0
            ]);
        }

        // Filter requested categories to only accessible ones
        $searchCategories = $request->get('categories', $accessibleCategories);
        $searchCategories = array_intersect($searchCategories, $accessibleCategories);

        if (empty($searchCategories)) {
            return response()->json([
                'error' => 'No accessible categories in search request',
                'results' => [],
                'total_count' => 0
            ]);
        }

        try {
            $response = Http::timeout(30)->post($this->ttsBackendUrl . '/api/motivationMessage/search', [
                'query' => $request->query,
                'categories' => $searchCategories,
                'limit' => $request->get('limit', 20)
            ]);
            
            if ($response->successful()) {
                $results = $response->json();
                
                return response()->json([
                    'success' => true,
                    'query' => $request->query,
                    'searched_categories' => $searchCategories,
                    'results' => $results,
                    'total_count' => count($results)
                ]);
            }

            return response()->json([
                'error' => 'Search temporarily unavailable'
            ], 503);

        } catch (\Exception $e) {
            Log::error('TTS Search Error', [
                'error' => $e->getMessage(),
                'query' => $request->query,
                'user_id' => $user->id
            ]);

            return response()->json([
                'error' => 'Search service temporarily unavailable'
            ], 503);
        }
    }

    /**
     * Get category-specific pricing for individual purchases
     */
    public function getCategoryPricing(Request $request)
    {
        $categories = $this->accessControlService->getAllTtsCategories();
        
        // Define pricing for individual category purchases
        $categoryPrices = [
            'Self Confidence' => 4.99,
            'Positive Attitude' => 4.99,
            'Quit Smoking' => 7.99,
            'Will Power' => 4.99,
            'Guided Visualization' => 6.99,
            'Hypnotherapy for Self Confidence' => 8.99,
            'Sleep Hypnosis' => 6.99,
            'Stress Relief' => 5.99,
            'Motivation' => 4.99,
            'Focus & Concentration' => 5.99
        ];

        $pricing = [];
        foreach ($categories as $category) {
            $pricing[] = [
                'category' => $category,
                'price' => $categoryPrices[$category] ?? 4.99,
                'description' => $this->getCategoryDescription($category),
                'estimated_messages' => rand(15, 45) // Placeholder
            ];
        }

        return response()->json([
            'individual_category_pricing' => $pricing,
            'bundle_savings' => 'Save up to 60% with subscription plans',
            'recommended_plan' => 'TTS Affirmations Complete - $14.99/month'
        ]);
    }

    /**
     * Helper method to get category descriptions
     */
    private function getCategoryDescription($category)
    {
        $descriptions = [
            'Self Confidence' => 'Build unshakeable self-confidence with powerful affirmations',
            'Positive Attitude' => 'Cultivate a positive mindset and optimistic outlook',
            'Quit Smoking' => 'Support your journey to quit smoking with targeted affirmations',
            'Will Power' => 'Strengthen your willpower and self-discipline',
            'Guided Visualization' => 'Experience powerful guided visualization sessions',
            'Hypnotherapy for Self Confidence' => 'Professional hypnotherapy for confidence building',
            'Sleep Hypnosis' => 'Deep relaxation and sleep improvement techniques',
            'Stress Relief' => 'Reduce stress and anxiety with calming affirmations',
            'Motivation' => 'Boost your motivation and drive for success',
            'Focus & Concentration' => 'Enhance your focus and mental clarity'
        ];

        return $descriptions[$category] ?? 'Transform your mindset with targeted affirmations';
    }

    /**
     * Get TTS audio products catalog with preview capability
     */
    public function getTtsProductsCatalog(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        // Get active categories
        $categories = TtsCategory::active()->ordered()->get();
        
        // Get active products grouped by category
        $products = TtsAudioProduct::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $catalog = [];
        foreach ($categories as $category) {
            $categoryProducts = $products->get($category->name, collect());
            
            $productList = $categoryProducts->map(function ($product) use ($user) {
                $hasAccess = $user->hasTtsProductAccess($product->id);
                $categoryAccess = $user->hasTtsCategoryAccessExtended($product->category);
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'category' => $product->category,
                    'language' => $product->language,
                    'price' => $product->price,
                    'formatted_price' => $product->formatted_price,
                    'preview_duration' => $product->preview_duration,
                    'cover_image_url' => $product->cover_image_url,
                    'total_messages_count' => $product->total_messages_count,
                    'sample_messages' => $product->sample_messages,
                    'has_access' => $hasAccess || $categoryAccess['has_access'],
                    'access_type' => $hasAccess ? 'individual_purchase' : $categoryAccess['access_type'],
                    'can_preview' => true,
                    'preview_available' => $product->hasPreviewSamples()
                ];
            });

            $catalog[] = [
                'category' => [
                    'name' => $category->name,
                    'display_name' => $category->display_name,
                    'description' => $category->description,
                    'icon_url' => $category->icon_url
                ],
                'products' => $productList,
                'user_has_category_access' => $user->hasTtsCategoryAccessExtended($category->name)['has_access']
            ];
        }

        return response()->json([
            'success' => true,
            'categories' => $catalog,
            'user_access_summary' => $user->getTtsAccessSummary()
        ]);
    }

    /**
     * Generate preview audio with background music
     */
    public function generatePreviewAudio(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:tts_audio_products,id',
            'message_text' => 'sometimes|string|max:500',
            'voice' => 'sometimes|string',
            'speed' => 'sometimes|numeric|min:0.5|max:2.0'
        ]);

        $product = TtsAudioProduct::findOrFail($request->product_id);
        
        // Use provided message or random sample message
        $messageText = $request->message_text ?? $product->getRandomSampleMessage();
        
        if (!$messageText) {
            return response()->json([
                'error' => 'No preview message available for this product'
            ], 400);
        }

        try {
            // Generate TTS audio via backend
            $ttsResponse = Http::timeout(60)->post($this->ttsBackendUrl . '/api/generate-preview', [
                'text' => $messageText,
                'voice' => $request->voice ?? 'default',
                'speed' => $request->speed ?? 1.0,
                'language' => $product->language,
                'preview_duration' => $product->preview_duration,
                'background_music_url' => $product->background_music_url
            ]);

            if ($ttsResponse->successful()) {
                $audioData = $ttsResponse->json();
                
                return response()->json([
                    'success' => true,
                    'preview_audio_url' => $audioData['preview_url'],
                    'duration' => $audioData['duration'] ?? $product->preview_duration,
                    'message_text' => $messageText,
                    'expires_at' => now()->addHours(2)->toISOString(), // Temporary URL
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'category' => $product->category
                    ]
                ]);
            }

            return response()->json([
                'error' => 'Failed to generate preview audio'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Preview Generation Error', [
                'error' => $e->getMessage(),
                'product_id' => $request->product_id,
                'message_text' => $messageText
            ]);

            return response()->json([
                'error' => 'Preview service temporarily unavailable'
            ], 503);
        }
    }

    /**
     * Get user's purchased TTS products
     */
    public function getUserTtsProducts(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $purchases = $user->completedTtsProductPurchases()
            ->with('product')
            ->orderBy('purchased_at', 'desc')
            ->get();

        $userProducts = $purchases->map(function ($purchase) {
            return [
                'purchase_id' => $purchase->id,
                'product' => [
                    'id' => $purchase->product->id,
                    'name' => $purchase->product->name,
                    'description' => $purchase->product->description,
                    'category' => $purchase->product->category,
                    'language' => $purchase->product->language,
                    'cover_image_url' => $purchase->product->cover_image_url,
                    'total_messages_count' => $purchase->product->total_messages_count
                ],
                'purchased_at' => $purchase->purchased_at,
                'amount_paid' => $purchase->amount
            ];
        });

        // Get accessible categories (including subscription access)
        $accessibleCategories = $user->getAccessibleTtsCategories();

        return response()->json([
            'success' => true,
            'purchased_products' => $userProducts,
            'accessible_categories' => $accessibleCategories,
            'access_summary' => $user->getTtsAccessSummary()
        ]);
    }
}
