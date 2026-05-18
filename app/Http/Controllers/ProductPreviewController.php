<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\AudioSecurityService;
use Illuminate\Http\Request;

class ProductPreviewController extends Controller
{
    protected $audioSecurityService;

    public function __construct(AudioSecurityService $audioSecurityService)
    {
        $this->audioSecurityService = $audioSecurityService;
    }

    /**
     * Get preview URL for a product
     */
    public function getPreviewUrl(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $product = Product::with(['linkedAudiobook.chapters'])->findOrFail($request->product_id);

        $previewUrl = $product->resolvePreviewUrl();
        if (!$previewUrl) {
            return response()->json(['error' => 'No audio file available'], 404);
        }

        try {
            return response()->json([
                'preview_url' => $previewUrl,
                'duration' => $product->previewDisplayDuration(),
                'product_name' => $product->name
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to generate preview'], 500);
        }
    }
}
