<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TtsCategory;
use App\Models\TtsAudioProduct;
use App\Services\AccessControlService;
use App\Services\AudioSecurityService;
use App\Services\AudioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TtsBackendController extends Controller
{
    private const PREVIEW_AUDIO_URL_MAX_LENGTH = 65535;

    protected $accessControlService;
    protected $audioService;
    protected $audioSecurityService;
    protected $ttsBackendUrl;

    public function __construct(AccessControlService $accessControlService, AudioService $audioService, AudioSecurityService $audioSecurityService)
    {
        $this->accessControlService = $accessControlService;
        $this->audioService = $audioService;
        $this->audioSecurityService = $audioSecurityService;
        $this->ttsBackendUrl = rtrim(config('services.tts.base_url'), '/api');
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
                if (is_string($item)) { $audioUrls[] = $this->normalizeAudioUrl($item); continue; }
                if (is_array($item)) {
                    $candidate = $item['url'] ?? $item['audio_url'] ?? $item['src'] ?? $item['path'] ?? null;
                    if (is_string($candidate)) { $audioUrls[] = $this->normalizeAudioUrl($candidate); }
                }
            }
        }
        $audioUrls = array_values(array_unique(array_filter($audioUrls)));

        // If this language-variant product has no audio, fall back to the 'en' product
        // in the same category that does have audio (transparent fallback for the Flutter app).
        $fallbackProductId = null;
        if (empty($audioUrls) && $product->language !== 'en') {
            $fallback = TtsAudioProduct::active()
                ->where('category', $product->category)
                ->where('language', 'en')
                ->whereNotNull('audio_urls')
                ->where('audio_urls', '!=', '[]')
                ->where('audio_urls', '!=', 'null')
                ->orderByDesc('total_messages_count')
                ->first();
            if ($fallback) {
                $fallbackRaw = $fallback->audio_urls;
                if (is_string($fallbackRaw)) {
                    $fallbackRaw = json_decode($fallbackRaw, true) ?? [];
                }
                foreach (($fallbackRaw ?: []) as $item) {
                    if (is_string($item)) { $audioUrls[] = $this->normalizeAudioUrl($item); continue; }
                    if (is_array($item)) {
                        $c = $item['url'] ?? $item['audio_url'] ?? $item['src'] ?? $item['path'] ?? null;
                        if (is_string($c)) { $audioUrls[] = $this->normalizeAudioUrl($c); }
                    }
                }
                $audioUrls = array_values(array_unique(array_filter($audioUrls)));
                $fallbackProductId = $fallback->id;
                Log::info('getTtsProductDetail: using en fallback tracks', [
                    'requested_product' => $product->id,
                    'fallback_product'  => $fallback->id,
                    'track_count'       => count($audioUrls),
                ]);
            }
        }

        // Load full message texts from tts_motivation_messages (keyed by slug for lookup)
        $messageTextMap = $this->buildProductMessageTextMap($product);

        // Map to tracks payload — extract message text from audio filename slug
        $tracks = [];
        foreach ($audioUrls as $i => $url) {
            $urlSlug  = $this->extractSlugFromUrl($url);
            $fullText = $urlSlug ? $this->findMessageTextForSlug($urlSlug, $messageTextMap) : null;
            $title    = $this->extractMessageTextFromUrl($url) ?: ($product->name . ' #' . ($i + 1));
            $tracks[] = [
                'index'        => $i,
                'url'          => $url,
                'title'        => $title,
                'message_text' => $fullText ?? $title,
            ];
        }

        if (!empty($tracks)) {
            // Use the fallback product for encryption look-ups if we're serving its tracks
            $encryptionProduct = $fallbackProductId
                ? (TtsAudioProduct::find($fallbackProductId) ?? $product)
                : $product;
            $tracks = $this->ensureEncryptedProductTracks($encryptionProduct, $tracks);
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
                'background_music_track' => $this->resolveBackgroundMusicPlaybackUrl($product),
                'background_music_track_name' => $product->background_music_track,
                'background_music_url' => $this->resolveBackgroundMusicPlaybackUrl($product),
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

        // Optional: restrict to admin or trial roles later.
        $base = storage_path('app/bg-music');
        $collections = [];
        $scanDirs = [
            'original' => $base . '/original',
            'encrypted' => $base . '/encrypted'
        ];
        foreach ($scanDirs as $variant => $dir) {
            if (!is_dir($dir)) continue;
            $files = collect(scandir($dir))
                ->reject(fn($f) => in_array($f, ['.', '..']))
                ->filter(fn($f) => preg_match('/\.(mp3|wav|m4a|aac|ogg)$/i', $f))
                ->values()
                ->map(function ($f) use ($variant) {
                    $relative = 'bg-music/' . $variant . '/' . $f;
                    return [
                        'file' => $f,
                        'variant' => $variant,
                        'path' => $relative,
                        'url' => $this->issueBackgroundMusicSecureUrl($f),
                    ];
                });
            $collections[$variant] = $files->toArray();
        }

        return response()->json([
            'success' => true,
            'variants' => $collections,
            'total' => array_sum(array_map('count', $collections))
        ]);
    }

    /**
     * Stream a background music file (auth required). Not signed because asset list is gated.
     */
    public function streamBackgroundMusic(Request $request, $variant, $file)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Authentication required'], 401);
        if (!in_array($variant, ['original','encrypted'])) return response()->json(['error'=>'invalid_variant'],422);
        $path = storage_path('app/bg-music/'.$variant.'/'.$file);
        if (!is_file($path)) return response()->json(['error'=>'not_found'],404);
        $mime = mime_content_type($path) ?: 'audio/mpeg';
        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600'
        ]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Extract human-readable message text from an audio file URL.
     *
     * Audio filenames are slugified message texts with a trailing MD5 hash.
     * e.g. "a-positive-attitude-can-really-make-dreams-come-true-<md5hash>.enc"
     * → "A positive attitude can really make dreams come true"
     *
     * Works with both signed Laravel URLs (base64-encoded path param) and
     * direct Node backend URLs.
     */

    /**
     * Build a slug → full message text map for a product by looking up its
     * corresponding tts_motivation_messages record.
     */
    private function buildProductMessageTextMap(TtsAudioProduct $product): array
    {
        if (empty($product->backend_category_id)) {
            return [];
        }

        $sourceCategory = \DB::table('tts_source_categories')
            ->where('mongo_id', $product->backend_category_id)
            ->first();

        if (!$sourceCategory) {
            return [];
        }

        // Prefer a record that matches the product's speaker; fall back to any record
        $msgRecord = \DB::table('tts_motivation_messages')
            ->where('source_category_id', $sourceCategory->id)
            ->when($product->backend_speaker, fn($q) => $q->where('speaker', $product->backend_speaker))
            ->first();

        if (!$msgRecord) {
            $msgRecord = \DB::table('tts_motivation_messages')
                ->where('source_category_id', $sourceCategory->id)
                ->first();
        }

        if (!$msgRecord || empty($msgRecord->messages)) {
            return [];
        }

        $messages = json_decode($msgRecord->messages, true);
        if (!is_array($messages)) {
            return [];
        }

        $map = [];
        foreach ($messages as $msg) {
            $slug = $this->slugifyMessage((string) $msg);
            if ($slug !== '') {
                $map[$slug] = (string) $msg;
            }
        }
        return $map;
    }

    /**
     * Normalise a message string into a URL-filename-compatible slug so it
     * can be matched against the slug extracted from an audio file path.
     */
    private function slugifyMessage(string $text): string
    {
        $text = strtolower($text);
        // Remove characters that are neither alphanumeric nor whitespace
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        // Collapse whitespace and trim
        $text = preg_replace('/\s+/', ' ', trim($text));
        return str_replace(' ', '-', $text);
    }

    /**
     * Extract only the filename slug (no hash, no extension) from an audio URL.
     * Returns an empty string when the URL cannot be parsed.
     */
    private function extractSlugFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        $filePath = '';
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $q);
            if (!empty($q['path'])) {
                $filePath = base64_decode($q['path'], true) ?: '';
            }
        }
        if (empty($filePath)) {
            $filePath = $parsed['path'] ?? '';
        }

        $basename = pathinfo($filePath, PATHINFO_FILENAME);
        if (empty($basename)) {
            return '';
        }

        // Strip trailing MD5 hash (32 hex chars preceded by a hyphen)
        $slug = preg_replace('/-[0-9a-f]{32}$/i', '', $basename);
        return strtolower($slug);
    }

    /**
     * Find the full message text for a given URL slug by checking the message
     * map. Handles the case where filenames were truncated at generation time
     * (the message slug will be longer than the URL slug).
     */
    private function findMessageTextForSlug(string $urlSlug, array $messageMap): ?string
    {
        if (isset($messageMap[$urlSlug])) {
            return $messageMap[$urlSlug];
        }

        // The file slug may be a truncated prefix of the full message slug
        foreach ($messageMap as $msgSlug => $text) {
            if (str_starts_with($msgSlug, $urlSlug)) {
                return $text;
            }
        }

        return null;
    }

    private function extractMessageTextFromUrl(string $url): string
    {
        // Try to get the file path – signed URLs encode it in ?path=<base64>
        $parsed = parse_url($url);
        $filePath = '';
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $q);
            if (!empty($q['path'])) {
                $filePath = base64_decode($q['path'], true) ?: '';
            }
        }
        if (empty($filePath)) {
            $filePath = $parsed['path'] ?? '';
        }

        // Get just the filename without directory
        $basename = pathinfo($filePath, PATHINFO_FILENAME);

        if (empty($basename)) {
            return '';
        }

        // Strip trailing MD5 hash (32 hex chars) preceded by a hyphen
        $text = preg_replace('/-[0-9a-f]{32}$/i', '', $basename);

        if (empty($text)) {
            return '';
        }

        // Convert slug to sentence: hyphens → spaces, capitalise first letter
        $text = str_replace('-', ' ', $text);
        return ucfirst($text);
    }

    /**
     * Normalise audio URLs – replace legacy hostnames with the current domain.
     */
    private function normalizeAudioUrl(string $url): string
    {
        $legacyPrefixes = [
            'https://meditative-brains.com:3001',
            'http://meditative-brains.com:3001',
            'https://motivation.mywebsolutions.co.in:3000',
            'http://motivation.mywebsolutions.co.in:3000',
            'https://motivation.mywebsolutions.co.in:3001',
            'http://motivation.mywebsolutions.co.in:3001',
        ];
        $newBase = 'https://mentalfitness.store:3001';
        foreach ($legacyPrefixes as $old) {
            if (str_starts_with($url, $old)) {
                return $newBase . substr($url, strlen($old));
            }
        }
        return $url;
    }

    /**
     * Return a signed, publicly playable URL for the product's background music.
     * Flutter/just_audio cannot use auth-gated API endpoints directly.
     */
    private function resolveBackgroundMusicPlaybackUrl(TtsAudioProduct $product): ?string
    {
        if (!$product->has_background_music) {
            Log::info('Product detail background music disabled', [
                'product_id' => $product->id,
                'background_music_track' => $product->background_music_track,
            ]);
            return null;
        }

        if (!empty($product->background_music_track)) {
            $signedUrl = $this->issueBackgroundMusicSecureUrl($product->background_music_track);
            if ($signedUrl) {
                Log::info('Resolved product background music from track', [
                    'product_id' => $product->id,
                    'track' => $product->background_music_track,
                    'url_length' => strlen($signedUrl),
                ]);
                return $signedUrl;
            }
        }

        if (!empty($product->background_music_url) && str_starts_with($product->background_music_url, 'http')) {
            $normalized = $this->normalizeAudioUrl($product->background_music_url);
            Log::info('Resolved product background music from stored URL', [
                'product_id' => $product->id,
                'background_music_url' => $product->background_music_url,
                'normalized_background_music_url' => $normalized,
            ]);
            return $normalized;
        }

        Log::warning('Unable to resolve product background music playback URL', [
            'product_id' => $product->id,
            'background_music_track' => $product->background_music_track,
            'background_music_url' => $product->background_music_url,
            'has_background_music' => $product->has_background_music,
        ]);

        return null;
    }

    /**
     * Resolve a bg-music track stem or filename to a signed stream URL.
     */
    private function issueBackgroundMusicSecureUrl(string $track): ?string
    {
        $track = trim($track);
        if ($track === '') {
            return null;
        }

        $dir = storage_path('app/bg-music/original');
        if (!is_dir($dir)) {
            Log::warning('Background music directory missing', ['dir' => $dir]);
            return null;
        }

        $candidate = null;
        foreach ((array)scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $base = pathinfo($item, PATHINFO_FILENAME);
            if (strcasecmp($base, pathinfo($track, PATHINFO_FILENAME)) === 0) {
                $candidate = $item;
                break;
            }
        }

        if (!$candidate) {
            Log::warning('Background music track file not found for secure URL', [
                'track' => $track,
                'dir' => $dir,
            ]);
            return null;
        }

        try {
            $encryptedPath = $this->audioSecurityService->encryptBgMusicFile($candidate);
            $url = $this->audioSecurityService->generateSecureUrl($encryptedPath, null, 30);
            Log::info('Issued background music secure URL', [
                'track' => $track,
                'candidate' => $candidate,
                'encrypted_path' => $encryptedPath,
                'url_length' => strlen($url),
            ]);
            return $url;
        } catch (\Throwable $e) {
            Log::warning('Failed issuing background music secure URL', [
                'track' => $track,
                'candidate' => $candidate,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Ensure product track URLs are served from Laravel-managed encrypted storage.
     * Raw Node URLs are copied into audio/original/tts-products and mirrored into
     * encrypted storage before signed URLs are returned to Flutter.
     */
    private function ensureEncryptedProductTracks(TtsAudioProduct $product, array $tracks): array
    {
        $secured = [];
        $updatedUrls = [];
        $changed = false;

        foreach ($tracks as $track) {
            $url = is_array($track) ? ($track['url'] ?? null) : null;
            if (!is_string($url) || $url === '') {
                continue;
            }

            $normalizedUrl = $this->normalizeAudioUrl($url);
            if (str_contains($normalizedUrl, '/audio/signed-stream')) {
                $refreshedSignedUrl = $this->refreshSignedProductTrackUrl($normalizedUrl);
                $track['url'] = $refreshedSignedUrl ?? $normalizedUrl;
                $secured[] = $track;
                $updatedUrls[] = $track['url'];
                if ($track['url'] !== $normalizedUrl) {
                    $changed = true;
                }
                continue;
            }

            $securedUrl = $this->mirrorProductTrackToSecureStorage(
                $product,
                (int) ($track['index'] ?? count($secured)),
                $normalizedUrl
            );

            if ($securedUrl) {
                $track['url'] = $securedUrl;
                $updatedUrls[] = $securedUrl;
                if ($securedUrl !== $normalizedUrl) {
                    $changed = true;
                }
            } else {
                $track['url'] = $normalizedUrl;
                $updatedUrls[] = $normalizedUrl;
            }

            $secured[] = $track;
        }

        if ($changed && !empty($updatedUrls)) {
            try {
                $product->update([
                    'audio_urls' => array_values($updatedUrls),
                    'preview_audio_url' => $this->getPersistablePreviewUrl($updatedUrls[0] ?? null, $product->preview_audio_url),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed persisting secured product audio URLs', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $secured;
    }

    private function refreshSignedProductTrackUrl(string $signedUrl): ?string
    {
        try {
            $query = parse_url($signedUrl, PHP_URL_QUERY);
            if (!is_string($query) || $query === '') {
                return null;
            }

            parse_str($query, $params);
            $encodedPath = $params['path'] ?? null;
            if (!is_string($encodedPath) || trim($encodedPath) === '') {
                return null;
            }

            $encryptedPath = base64_decode($encodedPath, true);
            if (!is_string($encryptedPath) || trim($encryptedPath) === '') {
                return null;
            }

            if (!Storage::disk('local')->exists($encryptedPath)) {
                Log::warning('Unable to refresh signed product track URL because encrypted file is missing', [
                    'signed_url' => Str::limit($signedUrl, 220),
                    'encrypted_path' => $encryptedPath,
                ]);
                return null;
            }

            $previewLength = $params['preview'] ?? null;
            $previewLength = is_numeric($previewLength) ? (int) $previewLength : null;

            // Use 5-year expiry for URLs served to the app to prevent silent playback failures
            $refreshedUrl = $this->audioSecurityService->generateSignedUrl($encryptedPath, $previewLength, 60 * 24 * 365 * 5);

            Log::info('Refreshed signed product track URL for product detail response', [
                'encrypted_path' => $encryptedPath,
                'old_url_length' => strlen($signedUrl),
                'new_url_length' => strlen($refreshedUrl),
            ]);

            return $refreshedUrl;
        } catch (\Throwable $e) {
            Log::warning('Failed refreshing signed product track URL', [
                'signed_url' => Str::limit($signedUrl, 220),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getPersistablePreviewUrl(?string $candidate, ?string $fallback = null): ?string
    {
        if (!is_string($candidate) || trim($candidate) === '') {
            return $fallback;
        }

        return strlen($candidate) <= self::PREVIEW_AUDIO_URL_MAX_LENGTH ? $candidate : $fallback;
    }

    private function mirrorProductTrackToSecureStorage(TtsAudioProduct $product, int $index, string $sourceUrl): ?string
    {
        try {
            $extension = pathinfo(parse_url($sourceUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            $extension = $extension !== '' ? strtolower($extension) : 'aac';
            $originalRelative = $this->buildProductOriginalRelativePath($product, $index, $sourceUrl, $extension);
            $originalStoragePath = 'audio/original/' . $originalRelative;

            if (!Storage::disk('local')->exists($originalStoragePath)) {
                $response = Http::timeout(90)->get($sourceUrl);
                if (!$response->successful()) {
                    Log::warning('Unable to mirror raw product audio', [
                        'product_id' => $product->id,
                        'index' => $index,
                        'status' => $response->status(),
                        'source_url' => $sourceUrl,
                    ]);
                    return null;
                }

                Storage::disk('local')->put($originalStoragePath, $response->body());
            }

            $encryptedPath = $this->audioSecurityService->encryptOriginalFile($originalRelative, $product->id);
            return $this->audioSecurityService->generateSignedUrl($encryptedPath, null, 60 * 24);
        } catch (\Throwable $e) {
            Log::warning('Failed securing product track', [
                'product_id' => $product->id,
                'index' => $index,
                'source_url' => Str::limit($sourceUrl, 160),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function buildProductOriginalRelativePath(TtsAudioProduct $product, int $index, string $sourceUrl, string $extension): string
    {
        $locale = $this->normalizeLocaleForStorage($product->backend_language ?: $product->language ?: 'en-US');
        $category = Str::slug($product->category ?: 'default');
        $productSlug = Str::slug($product->slug ?: $product->name ?: ('product-' . $product->id));
        $speaker = $this->extractSpeakerFromSourceUrl($sourceUrl) ?: 'unknown-speaker';
        $fileName = $this->buildTrackFileNameFromSourceUrl($sourceUrl, $index, $extension);

        return sprintf(
            'tts-products/%s/%s/%s/%s/%s',
            $locale,
            $category,
            $productSlug,
            $speaker,
            $fileName
        );
    }

    private function buildTrackFileNameFromSourceUrl(string $sourceUrl, int $index, string $extension): string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH) ?? '';
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $baseName = Str::limit(trim($baseName), 180, '');

        if ($baseName !== '') {
            $slugged = Str::slug($baseName, '-');
            if ($slugged !== '') {
                return $slugged . '.' . $extension;
            }
        }

        return sprintf('track-%02d.%s', $index + 1, $extension);
    }

    private function normalizeLocaleForStorage(string $language): string
    {
        $normalized = trim(str_replace('-', '_', $language));
        if ($normalized === '') {
            return 'en_US';
        }

        $parts = array_values(array_filter(explode('_', $normalized)));
        if (count($parts) === 1) {
            $base = strtolower($parts[0]);
            $region = $base === 'en' ? 'US' : strtoupper($parts[0]);
            return $base . '_' . $region;
        }

        return strtolower($parts[0]) . '_' . strtoupper($parts[1]);
    }

    private function extractSpeakerFromSourceUrl(string $sourceUrl): ?string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $storageRootIndex = array_search('audio-cache', $segments, true);
        $speakerOffset = 3;
        if ($storageRootIndex === false) {
            $storageRootIndex = array_search('products-audio', $segments, true);
            $speakerOffset = 4;
        }

        if ($storageRootIndex !== false) {
            $speaker = $segments[$storageRootIndex + $speakerOffset] ?? null;
            if (is_string($speaker) && $speaker !== '') {
                return $speaker;
            }
        }

        return null;
    }

}
