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

        $product = Product::findOrFail($request->product_id);
        
        if (!$product->audio_path) {
            return response()->json(['error' => 'No audio file available'], 404);
        }

        try {
            $previewUrl = $this->audioSecurityService->generateSignedUrl(
                $product->audio_path, 
                $product->preview_duration
            );

            return response()->json([
                'preview_url' => $previewUrl,
                'duration' => $product->preview_duration,
                'product_name' => $product->name
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to generate preview'], 500);
        }
    }
}
