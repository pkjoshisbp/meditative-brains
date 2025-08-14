<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MusicLibraryController extends Controller
{
    protected $accessControlService;

    public function __construct(AccessControlService $accessControlService)
    {
        $this->accessControlService = $accessControlService;
    }

    /**
     * Get music library categories and products for Flutter app
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get music categories (excluding TTS affirmations)
        $musicAudioTypes = ['sleep_aid', 'meditation', 'binaural_beats', 'nature_sounds', 'solfeggio'];
        
        $products = Product::whereIn('audio_type', $musicAudioTypes)
            ->where('is_active', true)
            ->with('category')
            ->get()
            ->groupBy('category.name');

        $response = [
            'categories' => [],
            'user_access' => null,
            'subscription_plans' => []
        ];

        // Build categories with access information
        foreach ($products as $categoryName => $categoryProducts) {
            $categoryData = [
                'name' => $categoryName,
                'products' => []
            ];

            foreach ($categoryProducts as $product) {
                $hasAccess = $user ? $user->hasMusicProductAccess($product->id) : false;
                
                $productData = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->short_description,
                    'price' => $product->sale_price ?? $product->price,
                    'original_price' => $product->price,
                    'audio_type' => $product->audio_type,
                    'audio_features' => $product->audio_features,
                    'preview_duration' => $product->preview_duration,
                    'preview_url' => $product->preview_file ? url('storage/' . $product->preview_file) : null,
                    'full_audio_url' => $hasAccess && $product->full_file ? url('storage/' . $product->full_file) : null,
                    'tags' => explode(',', $product->tags ?? ''),
                    'is_featured' => $product->is_featured,
                    'has_access' => $hasAccess,
                    'can_preview' => true
                ];

                $categoryData['products'][] = $productData;
            }

            $response['categories'][] = $categoryData;
        }

        // Add user access information
        if ($user) {
            $response['user_access'] = $this->accessControlService->getUserAccessSummary($user);
        }

        // Add available subscription plans
        $plans = SubscriptionPlan::where('includes_music_library', true)
            ->orWhere('includes_all_tts_categories', true)
            ->active()
            ->orderBy('sort_order')
            ->get();

        foreach ($plans as $plan) {
            $response['subscription_plans'][] = [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'features' => $plan->features,
                'includes_music_library' => $plan->includes_music_library,
                'includes_all_tts_categories' => $plan->includes_all_tts_categories,
                'trial_days' => $plan->trial_days,
                'is_featured' => $plan->is_featured
            ];
        }

        return response()->json($response);
    }

    /**
     * Check user's access to specific content
     */
    public function checkAccess(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $request->validate([
            'resource_type' => 'required|in:music_library,music_product,tts_category',
            'resource_id' => 'required_unless:resource_type,music_library'
        ]);

        $accessCheck = $this->accessControlService->canUserAccess(
            $user,
            $request->resource_type,
            $request->resource_id
        );

        return response()->json($accessCheck);
    }

    /**
     * Get user's music library with access status
     */
    public function myLibrary(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $musicAudioTypes = ['sleep_aid', 'meditation', 'binaural_beats', 'nature_sounds', 'solfeggio'];
        
        // Get products user has access to
        $accessibleProducts = [];
        
        if ($user->hasMusicLibraryAccess()) {
            // User has full library access
            $accessibleProducts = Product::whereIn('audio_type', $musicAudioTypes)
                ->where('is_active', true)
                ->get();
        } else {
            // Get individually purchased products
            $purchasedProducts = $user->getPurchasedProducts()
                ->filter(function ($product) use ($musicAudioTypes) {
                    return in_array($product->audio_type, $musicAudioTypes);
                });
            
            $accessibleProducts = $purchasedProducts;
        }

        $response = [
            'access_type' => $user->hasMusicLibraryAccess() ? 'full_library' : 'individual_purchases',
            'total_accessible' => $accessibleProducts->count(),
            'subscription_info' => $user->getActiveSubscription(),
            'products' => []
        ];

        foreach ($accessibleProducts as $product) {
            $response['products'][] = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->short_description,
                'audio_type' => $product->audio_type,
                'audio_features' => $product->audio_features,
                'full_audio_url' => $product->full_file ? url('storage/' . $product->full_file) : null,
                'preview_url' => $product->preview_file ? url('storage/' . $product->preview_file) : null,
                'category' => $product->category->name ?? 'Uncategorized',
                'tags' => explode(',', $product->tags ?? ''),
                'downloaded' => false // You can track this separately if needed
            ];
        }

        return response()->json($response);
    }

    /**
     * Get TTS categories with access information
     */
    public function ttsCategories(Request $request)
    {
        $user = Auth::user();
        
        $allCategories = $this->accessControlService->getAllTtsCategories();
        $userCategories = $user ? $user->getAccessibleTtsCategories() : [];

        $response = [
            'categories' => [],
            'user_access_summary' => []
        ];

        foreach ($allCategories as $category) {
            $hasAccess = in_array($category, $userCategories);
            
            $response['categories'][] = [
                'name' => $category,
                'has_access' => $hasAccess,
                'description' => $this->getCategoryDescription($category),
                'estimated_content_count' => $this->getEstimatedContentCount($category)
            ];
        }

        if ($user) {
            $response['user_access_summary'] = [
                'accessible_categories' => $userCategories,
                'total_accessible' => count($userCategories),
                'total_available' => count($allCategories),
                'subscription_access' => $user->getActiveSubscription() ? true : false
            ];
        }

        return response()->json($response);
    }

    /**
     * Preview a music track (available to all users)
     */
    public function preview(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        
        if (!$product->preview_file) {
            return response()->json([
                'error' => 'Preview not available for this product'
            ], 404);
        }

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'preview_url' => url('storage/' . $product->preview_file),
            'preview_duration' => $product->preview_duration,
            'full_access_required' => !Auth::user() || !Auth::user()->hasMusicProductAccess($product->id)
        ]);
    }

    /**
     * Get full audio file (requires access)
     */
    public function getFullAudio(Request $request, $productId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required'
            ], 401);
        }

        $product = Product::findOrFail($productId);
        
        if (!$user->hasMusicProductAccess($product->id)) {
            return response()->json([
                'error' => 'Access denied. Purchase required.',
                'product_name' => $product->name,
                'price' => $product->sale_price ?? $product->price,
                'can_purchase' => true
            ], 403);
        }

        if (!$product->full_file) {
            return response()->json([
                'error' => 'Full audio file not available'
            ], 404);
        }

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'audio_url' => url('storage/' . $product->full_file),
            'audio_type' => $product->audio_type,
            'audio_features' => $product->audio_features
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
            'Hypnotherapy for Self Confidence' => 'Professional hypnotherapy for confidence building'
        ];

        return $descriptions[$category] ?? 'Transform your mindset with targeted affirmations';
    }

    /**
     * Helper method to estimate content count per category
     */
    private function getEstimatedContentCount($category)
    {
        // This could be dynamic based on actual content in your TTS backend
        return rand(15, 45); // Placeholder
    }
}
