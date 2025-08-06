<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AudioSecurityService;
use Illuminate\Http\Response;

class AudioStreamController extends Controller
{
    private $audioSecurityService;

    public function __construct(AudioSecurityService $audioSecurityService)
    {
        $this->audioSecurityService = $audioSecurityService;
    }

    /**
     * Stream encrypted audio with secure token
     */
    public function stream(Request $request)
    {
        try {
            $token = $request->get('token');
            $expires = $request->get('expires');
            $signature = $request->get('signature');

            if (!$token || !$expires || !$signature) {
                return response('Invalid parameters', 400);
            }

            // Get decrypted audio content
            $audioContent = $this->audioSecurityService->getAudioFromToken($token, $expires, $signature);

            // Determine content type based on audio format
            $contentType = $this->getContentType($audioContent);

            return response($audioContent)
                ->header('Content-Type', $contentType)
                ->header('Accept-Ranges', 'bytes')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('Content-Disposition', 'inline'); // Prevent download, force streaming
        } catch (\Exception $e) {
            return response('Unauthorized or expired', 403);
        }
    }

    /**
     * Generate preview URL for a product
     */
    public function generatePreviewUrl(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'preview_length' => 'nullable|integer|min:10|max:600' // 10 seconds to 10 minutes
        ]);

        $product = \App\Models\Product::findOrFail($request->product_id);
        
        // Check if user has access (implement your own logic)
        if (!$this->canAccessPreview($product, auth()->user())) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $previewLength = $request->preview_length ?: $product->preview_duration;
        
        if ($product->full_file) {
            $url = $this->audioSecurityService->generateSecureUrl(
                $product->full_file, 
                $previewLength, 
                30 // 30 minutes expiry for preview
            );

            return response()->json([
                'preview_url' => $url,
                'duration' => $previewLength,
                'expires_in' => 30 * 60 // seconds
            ]);
        }

        return response()->json(['error' => 'No audio file available'], 404);
    }

    /**
     * Generate full audio URL for purchased products
     */
    public function generateFullAudioUrl(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $product = \App\Models\Product::findOrFail($request->product_id);
        
        // Check if user has purchased the product or has active subscription
        if (!$this->canAccessFullAudio($product, auth()->user())) {
            return response()->json(['error' => 'Purchase required'], 403);
        }

        if ($product->full_file) {
            $url = $this->audioSecurityService->generateSecureUrl(
                $product->full_file, 
                null, // No preview limit for purchased content
                120 // 2 hours expiry for full audio
            );

            return response()->json([
                'audio_url' => $url,
                'expires_in' => 120 * 60 // seconds
            ]);
        }

        return response()->json(['error' => 'No audio file available'], 404);
    }

    /**
     * Check if user can access preview
     */
    private function canAccessPreview($product, $user = null)
    {
        // Allow previews for active products
        return $product->is_active;
    }

    /**
     * Check if user can access full audio
     */
    private function canAccessFullAudio($product, $user = null)
    {
        if (!$user) {
            return false;
        }

        // Check if user has active subscription
        $hasActiveSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();

        if ($hasActiveSubscription) {
            return true;
        }

        // Check if user has purchased this specific product
        $hasPurchased = $user->orders()
            ->where('status', 'completed')
            ->whereJsonContains('order_items', function ($query) use ($product) {
                return $query->where('product_id', $product->id);
            })
            ->exists();

        return $hasPurchased;
    }

    /**
     * Stream audio using Laravel signed URLs
     */
    public function signedStream(Request $request)
    {
        // Laravel automatically validates signed URLs
        try {
            $encodedPath = $request->get('path');
            $previewLength = $request->get('preview');

            if (!$encodedPath) {
                return response('Invalid parameters', 400);
            }

            // Get decrypted audio content
            $audioContent = $this->audioSecurityService->streamFromSignedUrl($encodedPath, $previewLength);

            // Determine content type based on audio format
            $contentType = $this->getContentType($audioContent);

            return response($audioContent)
                ->header('Content-Type', $contentType)
                ->header('Accept-Ranges', 'bytes')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('Content-Disposition', 'inline'); // Prevent download, force streaming
        } catch (\Exception $e) {
            return response('Unauthorized or file not found', 403);
        }
    }
    private function getContentType($audioContent)
    {
        $header = substr($audioContent, 0, 10);
        
        if (substr($header, 0, 3) === 'ID3' || substr($header, 0, 2) === "\xFF\xFB") {
            return 'audio/mpeg';
        } elseif (substr($header, 0, 4) === 'RIFF') {
            return 'audio/wav';
        } elseif (substr($header, 0, 4) === 'OggS') {
            return 'audio/ogg';
        }
        
        return 'audio/mpeg'; // Default to MP3
    }
}
