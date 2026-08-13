<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AudioSecurityService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                ->header('Content-Disposition', 'inline');
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
            'preview_length' => 'nullable|integer|min:10|max:600'
        ]);

        $product = \App\Models\Product::findOrFail($request->product_id);

        if (!$this->canAccessPreview($product, auth()->user())) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $previewLength = $request->preview_length ?: $product->preview_duration;
        if ($product->full_file) {
            $url = $this->audioSecurityService->generateSecureUrl(
                $product->full_file,
                $previewLength,
                30
            );

            return response()->json([
                'preview_url' => $url,
                'duration' => $previewLength,
                'expires_in' => 30 * 60
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

        if (!$this->canAccessFullAudio($product, auth()->user())) {
            return response()->json(['error' => 'Purchase required'], 403);
        }

        if ($product->full_file) {
            $url = $this->audioSecurityService->generateSecureUrl(
                $product->full_file,
                null,
                120
            );

            return response()->json([
                'audio_url' => $url,
                'expires_in' => 120 * 60
            ]);
        }

        return response()->json(['error' => 'No audio file available'], 404);
    }

    private function canAccessPreview($product, $user = null)
    {
        return $product->is_active;
    }

    private function canAccessFullAudio($product, $user = null)
    {
        if (!$user) {
            return false;
        }

        $hasActiveSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();

        if ($hasActiveSubscription) {
            return true;
        }

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
        $startedAt = microtime(true);
        $requestId = (string) Str::uuid();
        $encodedPath = $request->get('path');
        $previewLength = $request->get('preview');
        $decodedPath = $encodedPath ? (base64_decode($encodedPath, true) ?: $encodedPath) : null;

        Log::info('Audio signed stream request', [
            'request_id' => $requestId,
            'path' => $decodedPath,
            'preview' => $previewLength !== null ? (int) $previewLength : null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 240),
            'range' => $request->header('Range'),
            'content_length_header' => $request->header('Content-Length'),
        ]);

        try {
            if (!$encodedPath) {
                return response('Invalid parameters', 400)
                    ->header('X-Audio-Request-Id', $requestId);
            }

            $audioContent = $this->audioSecurityService->streamFromSignedUrl($encodedPath, $previewLength);
            $contentType = $this->getContentType($audioContent);
            $byteLength = strlen($audioContent);

            Log::info('Audio signed stream served', [
                'request_id' => $requestId,
                'path' => $decodedPath,
                'preview' => $previewLength !== null ? (int) $previewLength : null,
                'content_type' => $contentType,
                'bytes' => $byteLength,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response($audioContent)
                ->header('Content-Type', $contentType)
                ->header('Content-Length', (string) $byteLength)
                ->header('Accept-Ranges', 'bytes')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('X-Audio-Request-Id', $requestId)
                ->header('Content-Disposition', 'inline');
        } catch (\Throwable $e) {
            Log::warning('Audio signed stream failed', [
                'request_id' => $requestId,
                'path' => $decodedPath ?? $request->get('path'),
                'preview' => $request->get('preview'),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            return response('Unauthorized or file not found', 403)
                ->header('X-Audio-Request-Id', $requestId);
        }
    }

    private function getContentType($audioContent)
    {
        $header = substr($audioContent, 0, 10);

        if (substr($header, 0, 3) === 'ID3' || substr($header, 0, 2) === "\xFF\xFB") {
            return 'audio/mpeg';
        } elseif (substr($header, 0, 2) === "\xFF\xF1" || substr($header, 0, 2) === "\xFF\xF9") {
            return 'audio/aac';
        } elseif (substr($header, 4, 4) === 'ftyp') {
            return 'audio/mp4';
        } elseif (substr($header, 0, 4) === 'RIFF') {
            return 'audio/wav';
        } elseif (substr($header, 0, 4) === 'OggS') {
            return 'audio/ogg';
        }

        return 'audio/aac';
    }
}
