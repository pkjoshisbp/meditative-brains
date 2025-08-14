<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Services\AudioSecurityService;
use App\Services\TTSIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class FlutterApiController extends Controller
{
    protected $audioService;
    protected $ttsService;

    public function __construct(AudioSecurityService $audioService, TTSIntegrationService $ttsService)
    {
        $this->audioService = $audioService;
        $this->ttsService = $ttsService;
    }

    /**
     * Get all categories with product counts
     */
    public function getCategories(): JsonResponse
    {
        try {
            $categories = Category::withCount('products')->get();
            
            return response()->json([
                'success' => true,
                'categories' => $categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                        'product_count' => $category->products_count,
                        'created_at' => $category->created_at->toISOString(),
                        'updated_at' => $category->updated_at->toISOString()
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Flutter API: Failed to get categories', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to get categories'], 500);
        }
    }

    /**
     * Get products by category
     */
    public function getProductsByCategory($categoryId): JsonResponse
    {
        try {
            $products = Product::where('category_id', $categoryId)
                ->select(['id', 'name', 'description', 'price', 'audio_features', 'created_at'])
                ->get();

            return response()->json([
                'success' => true,
                'products' => $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'price' => $product->price,
                        'audio_features' => $product->audio_features ? json_decode($product->audio_features, true) : null,
                        'preview_url' => route('api.flutter.audio.preview', $product->id),
                        'download_url' => route('api.flutter.audio.download', $product->id),
                        'created_at' => $product->created_at->toISOString()
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Flutter API: Failed to get products by category', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to get products'], 500);
        }
    }

    /**
     * Get all products with pagination
     */
    public function getAllProducts(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);
            
            $products = Product::with('category')
                ->select(['id', 'name', 'description', 'price', 'category_id', 'audio_features', 'created_at'])
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'products' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Flutter API: Failed to get all products', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to get products'], 500);
        }
    }

    /**
     * Get audio preview URL (30-second preview)
     */
    public function getAudioPreview($productId): JsonResponse
    {
        try {
            $product = Product::findOrFail($productId);
            
            if (!$product->audio_path) {
                return response()->json(['success' => false, 'message' => 'No audio file available'], 404);
            }

            // Generate signed URL for preview (valid for 1 hour)
            $previewUrl = $this->audioService->generateSignedUrl($product->audio_path, 3600, true);
            
            return response()->json([
                'success' => true,
                'preview_url' => $previewUrl,
                'expires_in' => 3600 // seconds
            ]);
        } catch (\Exception $e) {
            Log::error('Flutter API: Failed to get audio preview', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to get preview'], 500);
        }
    }

    /**
     * Get full audio download URL (requires authentication/purchase validation)
     */
    public function getAudioDownload($productId): JsonResponse
    {
        try {
            // TODO: Add authentication and purchase validation here
            // For now, treating as free content
            
            $product = Product::findOrFail($productId);
            
            if (!$product->audio_path) {
                return response()->json(['success' => false, 'message' => 'No audio file available'], 404);
            }

            // Generate signed URL for full download (valid for 24 hours)
            $downloadUrl = $this->audioService->generateSignedUrl($product->audio_path, 86400, false);
            
            return response()->json([
                'success' => true,
                'download_url' => $downloadUrl,
                'expires_in' => 86400 // seconds
            ]);
        } catch (\Exception $e) {
            Log::error('Flutter API: Failed to get audio download', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to get download URL'], 500);
        }
    }

    /**
     * Generate TTS on-demand for Flutter app
     */
    public function generateTTS(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:1000',
            'speaker' => 'string',
            'language' => 'string',
            'category_id' => 'required|exists:categories,id'
        ]);

        try {
            // Generate TTS audio
            $ttsResult = $this->ttsService->generateTTSAudio([
                'text' => $request->text,
                'speaker' => $request->speaker ?? 'en-US-AriaNeural',
                'language' => $request->language ?? 'en-US',
                'prosodyRate' => $request->prosody_rate ?? 'medium',
                'prosodyPitch' => $request->prosody_pitch ?? 'medium',
                'prosodyVolume' => $request->prosody_volume ?? 'medium'
            ]);

            if (!$ttsResult) {
                return response()->json(['success' => false, 'message' => 'Failed to generate TTS audio'], 500);
            }

            // Import as temporary product (or return direct URL based on requirements)
            $encryptedPath = $this->ttsService->importTTSAsProduct($ttsResult, $request->category_id);

            // Create temporary product record
            $product = Product::create([
                'name' => 'TTS: ' . substr($request->text, 0, 50),
                'description' => 'Generated TTS: ' . $request->text,
                'category_id' => $request->category_id,
                'price' => 0,
                'audio_path' => $encryptedPath,
                'audio_features' => json_encode([
                    'speaker' => $request->speaker ?? 'en-US-AriaNeural',
                    'language' => $request->language ?? 'en-US',
                    'source' => 'tts_flutter',
                    'generated_at' => now()->toISOString()
                ])
            ]);

            // Generate signed URLs
            $previewUrl = $this->audioService->generateSignedUrl($encryptedPath, 3600, true);
            $downloadUrl = $this->audioService->generateSignedUrl($encryptedPath, 86400, false);

            return response()->json([
                'success' => true,
                'product_id' => $product->id,
                'text' => $request->text,
                'speaker' => $request->speaker ?? 'en-US-AriaNeural',
                'preview_url' => $previewUrl,
                'download_url' => $downloadUrl,
                'expires_in' => 86400
            ]);

        } catch (\Exception $e) {
            Log::error('Flutter API: Failed to generate TTS', [
                'text' => $request->text,
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['success' => false, 'message' => 'Failed to generate TTS'], 500);
        }
    }

    /**
     * Search products
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        try {
            $query = $request->get('query');
            $perPage = $request->get('per_page', 20);
            
            $products = Product::with('category')
                ->where('name', 'LIKE', "%{$query}%")
                ->orWhere('description', 'LIKE', "%{$query}%")
                ->select(['id', 'name', 'description', 'price', 'category_id', 'audio_features', 'created_at'])
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'query' => $query,
                'products' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Flutter API: Failed to search products', [
                'query' => $request->get('query'),
                'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'message' => 'Search failed'], 500);
        }
    }
}
