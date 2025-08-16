<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TtsAudioProduct;
use App\Models\TtsCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TtsProductController extends Controller
{
    private $ttsBackendUrl = 'https://localhost:3001';

    /**
     * Display TTS products with backend categories for reference
     */
    public function index()
    {
        $products = TtsAudioProduct::latest()->paginate(15);
        $backendCategories = $this->getBackendCategories();
        
        return view('admin.tts-products.index', compact('products', 'backendCategories'));
    }

    /**
     * Show form for creating new TTS product
     */
    public function create()
    {
        $categories = TtsCategory::all();
        $backendCategories = $this->getBackendCategories();
        
        return view('admin.tts-products.create', compact('categories', 'backendCategories'));
    }

    /**
     * Store a newly created TTS product
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|exists:tts_categories,name',
            'language' => 'required|string|default:en-US',
            'price' => 'required|numeric|min:0',
            'backend_category_id' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $product = TtsAudioProduct::create($validated);

        return redirect()->route('admin.tts-products.index')
            ->with('success', 'TTS Product created successfully!');
    }

    /**
     * Display the specified TTS product
     */
    public function show(TtsAudioProduct $ttsProduct)
    {
        return view('admin.tts-products.show', compact('ttsProduct'));
    }

    /**
     * Show form for editing TTS product
     */
    public function edit(TtsAudioProduct $ttsProduct)
    {
        $categories = TtsCategory::all();
        $backendCategories = $this->getBackendCategories();
        
        return view('admin.tts-products.edit', compact('ttsProduct', 'categories', 'backendCategories'));
    }

    /**
     * Update the specified TTS product
     */
    public function update(Request $request, TtsAudioProduct $ttsProduct)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|exists:tts_categories,name',
            'language' => 'required|string',
            'price' => 'required|numeric|min:0',
            'backend_category_id' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $ttsProduct->update($validated);

        return redirect()->route('admin.tts-products.index')
            ->with('success', 'TTS Product updated successfully!');
    }

    /**
     * Remove the specified TTS product
     */
    public function destroy(TtsAudioProduct $ttsProduct)
    {
        $ttsProduct->delete();

        return redirect()->route('admin.tts-products.index')
            ->with('success', 'TTS Product deleted successfully!');
    }

    /**
     * Get backend categories for reference
     */
    private function getBackendCategories()
    {
        try {
            $response = Http::timeout(10)->withoutVerifying()->get($this->ttsBackendUrl . '/api/category');
            
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Backend categories unavailable: ' . $e->getMessage());
        }
        
        return [];
    }

    /**
     * Test backend connection
     */
    public function testConnection()
    {
        try {
            $response = Http::timeout(5)->withoutVerifying()->get($this->ttsBackendUrl . '/api/health');
            
            return response()->json([
                'success' => $response->successful(),
                'data' => $response->successful() ? $response->json() : null,
                'message' => $response->successful() ? 'Backend connected successfully' : 'Backend connection failed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backend connection failed: ' . $e->getMessage()
            ]);
        }
    }
}
