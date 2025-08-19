<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TtsCategory;
use App\Models\TtsAudioProduct;
use App\Services\AccessControlService;
use App\Services\AudioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TtsBackendController extends Controller
{
    protected $accessControlService;
    protected $audioService;
    protected $ttsBackendUrl;

    public function __construct(AccessControlService $accessControlService, AudioService $audioService)
    {
        $this->accessControlService = $accessControlService;
        $this->audioService = $audioService;
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
        $language = $request->get('language', 'en');
        
        try {
            $voicesData = $this->audioService->getAvailableVoices($language);
            
            return response()->json([
                'success' => true,
                'language' => $language,
                'voices' => $voicesData['voices'],
                'source' => $voicesData['source'] ?? 'backend',
                'total_count' => count($voicesData['voices'])
            ]);

        } catch (\Exception $e) {
            Log::error('TTS Voices Fetch Error', [
                'error' => $e->getMessage(),
                'language' => $language
            ]);

            return response()->json([
                'error' => 'Failed to fetch available voices',
                'language' => $language,
                'voices' => []
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
                'error' => 'No preview message available for this product',
                'product_id' => $product->id,
                'has_samples' => $product->hasPreviewSamples()
            ], 400);
        }

        try {
            // Use AudioService for enhanced preview generation
            $audioResult = $this->audioService->generatePreview([
                'text' => $messageText,
                'voice' => $request->voice ?? 'default',
                'speed' => $request->speed ?? 1.0,
                'language' => $product->language,
                'preview_duration' => $product->preview_duration,
                'background_music_url' => $product->background_music_url
            ]);

            return response()->json([
                'success' => true,
                'preview_audio_url' => $audioResult['preview_url'],
                'duration' => $audioResult['duration'] ?? $product->preview_duration,
                'message_text' => $messageText,
                'expires_at' => now()->addHours(2)->toISOString(),
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'has_background_music' => !empty($product->background_music_url)
                ],
                'generation_info' => [
                    'voice_used' => $request->voice ?? 'default',
                    'speed_used' => $request->speed ?? 1.0,
                    'cached' => isset($audioResult['cached']) ? $audioResult['cached'] : false
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Enhanced Preview Generation Error', [
                'error' => $e->getMessage(),
                'product_id' => $request->product_id,
                'message_text' => substr($messageText, 0, 100),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'error' => 'Preview service temporarily unavailable',
                'details' => config('app.debug') ? $e->getMessage() : 'Please try again later',
                'product_id' => $product->id
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

    /**
     * Generate preview for multiple sample messages (bulk preview)
     */
    public function generateBulkPreview(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:tts_audio_products,id',
            'voice' => 'sometimes|string',
            'speed' => 'sometimes|numeric|min:0.5|max:2.0',
            'count' => 'sometimes|integer|min:1|max:3'
        ]);

        $product = TtsAudioProduct::findOrFail($request->product_id);
        $count = min($request->get('count', 3), count($product->sample_messages ?? []));
        
        if (!$product->hasPreviewSamples()) {
            return response()->json([
                'error' => 'No preview samples available for this product',
                'product_id' => $product->id
            ], 400);
        }

        try {
            $previews = [];
            $sampleMessages = array_slice($product->sample_messages, 0, $count);
            
            foreach ($sampleMessages as $index => $message) {
                try {
                    $audioResult = $this->audioService->generatePreview([
                        'text' => $message,
                        'voice' => $request->voice ?? 'default',
                        'speed' => $request->speed ?? 1.0,
                        'language' => $product->language,
                        'preview_duration' => $product->preview_duration,
                        'background_music_url' => $product->background_music_url
                    ]);

                    $previews[] = [
                        'index' => $index,
                        'message' => $message,
                        'preview_url' => $audioResult['preview_url'],
                        'duration' => $audioResult['duration'],
                        'success' => true
                    ];
                } catch (\Exception $e) {
                    $previews[] = [
                        'index' => $index,
                        'message' => $message,
                        'error' => 'Failed to generate preview',
                        'success' => false
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category
                ],
                'previews' => $previews,
                'total_generated' => count(array_filter($previews, fn($p) => $p['success'])),
                'expires_at' => now()->addHours(2)->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk Preview Generation Error', [
                'error' => $e->getMessage(),
                'product_id' => $request->product_id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'error' => 'Bulk preview generation failed',
                'product_id' => $product->id
            ], 500);
        }
    }

    /**
     * Get audio service status and statistics
     */
    public function getAudioServiceStatus(Request $request)
    {
        try {
            $stats = $this->audioService->getGenerationStats();
            
            // Test backend connectivity
            $backendStatus = 'unknown';
            try {
                $response = Http::timeout(5)->get($this->ttsBackendUrl . '/health');
                $backendStatus = $response->successful() ? 'operational' : 'error';
            } catch (\Exception $e) {
                $backendStatus = 'unreachable';
            }

            return response()->json([
                'success' => true,
                'audio_service' => $stats,
                'backend_status' => $backendStatus,
                'backend_url' => $this->ttsBackendUrl,
                'features' => [
                    'preview_generation' => true,
                    'background_music_mixing' => true,
                    'caching' => true,
                    'bulk_preview' => true,
                    'voice_selection' => true
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get service status',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * List distinct active product languages user can actually access (purchased or via subscription/category access)
     */
    public function getAvailableLanguages(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required'],401);
        }

        // Distinct languages among active products
        $allLanguages = TtsAudioProduct::active()
            ->whereNotNull('language')
            ->pluck('language')
            ->unique()
            ->values();

        // Filter to languages where user has at least one accessible product (purchase or category access)
        $accessible = [];
        foreach ($allLanguages as $lang) {
            $products = TtsAudioProduct::active()->where('language',$lang)->get();
            $accessibleProducts = $products->filter(function($p) use ($user){
                $catAccess = $user->hasTtsCategoryAccessExtended($p->category);
                return $user->hasTtsProductAccess($p->id) || $catAccess['has_access'];
            });
            if ($accessibleProducts->count()) {
                $accessible[] = $lang;
            }
        }

        return response()->json([
            'success' => true,
            'languages' => array_values($accessible),
            'total' => count($accessible)
        ]);
    }

    /**
     * Products for a specific language filtered by user access (only return accessible products unless preview=1)
     */
    public function getProductsByLanguage(Request $request, $language)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required'],401);
        }

        $preview = (bool)$request->get('preview', false); // allow listing for marketing with preview=1

        $query = TtsAudioProduct::active()->where('language',$language);
        $products = $query->orderBy('sort_order')->orderBy('name')->get();

        $payload = [];
        foreach ($products as $product) {
            $catAccess = $user->hasTtsCategoryAccessExtended($product->category);
            $hasProduct = $user->hasTtsProductAccess($product->id);
            if (!$preview && !$hasProduct && !$catAccess['has_access']) {
                continue; // skip inaccessible unless preview mode
            }
            $payload[] = [
                'id' => $product->id,
                'name' => $product->name,
                'display_name' => $product->display_name,
                'category' => $product->category,
                'language' => $product->language,
                'price' => $product->price,
                'formatted_price' => $product->formatted_price,
                'has_access' => $hasProduct || $catAccess['has_access'],
                'access_type' => $hasProduct ? 'product_purchase' : ($catAccess['has_access'] ? $catAccess['access_type'] : 'none'),
                'preview_available' => $product->hasPreviewSamples(),
                'sample_messages' => $product->hasPreviewSamples() ? $product->sample_messages : [],
            ];
        }

        return response()->json([
            'success' => true,
            'language' => $language,
            'products' => $payload,
            'count' => count($payload),
            'preview_mode' => $preview
        ]);
    }

    /**
     * Detailed product info including pre-generated audio track URLs (requires access)
     */
    public function getTtsProductDetail(Request $request, $productId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $product = TtsAudioProduct::active()->findOrFail($productId);

        $catAccess = $user->hasTtsCategoryAccessExtended($product->category);
        $hasProduct = $user->hasTtsProductAccess($product->id);
        if (!$hasProduct && !$catAccess['has_access']) {
            return response()->json([
                'error' => 'Access denied',
                'reason' => 'No entitlement for this product or category',
                'product_id' => $product->id,
                'category' => $product->category,
                'available_for_purchase' => true
            ], 403);
        }

        // Normalize audio_urls similar to admin manager logic
        $raw = $product->audio_urls;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) { $raw = $decoded; } else { $raw = []; }
        }
        $audioUrls = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_string($item)) { $audioUrls[] = $item; continue; }
                if (is_array($item)) {
                    $candidate = $item['url'] ?? $item['audio_url'] ?? $item['src'] ?? $item['path'] ?? null;
                    if (is_string($candidate)) { $audioUrls[] = $candidate; }
                }
            }
        }
        $audioUrls = array_values(array_unique(array_filter($audioUrls)));

        // Map to tracks payload (no per-track text currently; placeholder uses product name + index)
        $tracks = [];
        foreach ($audioUrls as $i => $url) {
            $tracks[] = [
                'index' => $i,
                'url' => $url,
                'title' => $product->name . ' #' . ($i+1),
            ];
        }

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'display_name' => $product->display_name,
                'category' => $product->category,
                'language' => $product->language,
                'total_tracks' => count($tracks),
                'has_background_music' => $product->has_background_music,
                'background_music_track' => $product->background_music_track,
            ],
            'tracks' => $tracks,
            'access' => [
                'has_access' => true,
                'access_type' => $hasProduct ? 'product_purchase' : $catAccess['access_type']
            ]
        ]);
    }

    /**
     * List available background music tracks (original + encrypted variants) for selection.
     * Returns logical name, relative storage path, variant type.
     */
    public function listBackgroundMusic(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Return only encrypted files for Flutter to decrypt
        $encryptedDir = storage_path('app/bg-music/encrypted');
        $tracks = [];
        
        if (is_dir($encryptedDir)) {
            $files = collect(scandir($encryptedDir))
                ->reject(fn($f) => in_array($f, ['.', '..']))
                ->filter(fn($f) => str_ends_with($f, '.enc'))
                ->values()
                ->map(function ($f) {
                    // Remove .enc extension for display name
                    $displayName = preg_replace('/\.enc$/', '', $f);
                    $relative = 'bg-music/encrypted/' . $f;
                    return [
                        'file' => $displayName, // without .enc for UI
                        'encrypted_file' => $f, // actual file with .enc
                        'variant' => 'encrypted',
                        'path' => $relative,
                        'url' => route('bg.music.stream', ['variant' => 'encrypted', 'file' => $f], false)
                    ];
                });
            $tracks = $files->toArray();
        }

        return response()->json([
            'success' => true,
            'variants' => [
                'encrypted' => $tracks
            ],
            'total' => count($tracks)
        ]);
    }

    /**
     * Stream a background music file (auth required). Returns encrypted files for Flutter to decrypt.
     */
    public function streamBackgroundMusic(Request $request, $variant, $file)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Authentication required'], 401);
        
        if (!in_array($variant, ['encrypted'])) {
            return response()->json(['error'=>'Only encrypted variant supported'],422);
        }
        
        $path = storage_path('app/bg-music/encrypted/'.$file);
        if (!is_file($path)) {
            return response()->json(['error'=>'File not found'],404);
        }
        
        // Return encrypted file - Flutter will decrypt
        return response()->file($path, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Disposition' => 'attachment; filename="'.$file.'"'
        ]);
    }

    /**
     * Get encryption key for Flutter to decrypt background music files.
     * Returns the SHA256 hash of APP_KEY used for AES-256-CBC decryption.
     */
    public function getEncryptionKey(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Return the same key derivation used in AudioSecurityService
        $key = hash('sha256', config('app.key'), true);
        $keyBase64 = base64_encode($key);

        return response()->json([
            'success' => true,
            'encryption_key' => $keyBase64,
            'algorithm' => 'AES-256-CBC',
            'iv_length' => 16,
            'format' => 'First 16 bytes are IV, remainder is encrypted content'
        ]);
    }
}
