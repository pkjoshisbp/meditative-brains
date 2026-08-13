<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TtsAudioProduct;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class EntitlementController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $musicSummary = $user->getMusicAccessSummary();
        $ttsSummary = $user->getTtsAccessSummary();
        $deviceCount = $user->devices()->count();
        return response()->json([
            'music' => $musicSummary,
            'tts' => $ttsSummary,
            'device_limit' => $user->device_limit ?? 2,
            'device_count' => $deviceCount,
            'devices' => $user->devices()->orderByDesc('last_seen_at')->limit(10)->get(),
        ]);
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'device_uuid' => 'required|string|max:100',
            'platform' => 'nullable|string|max:40',
            'model' => 'nullable|string|max:120',
            'app_version' => 'nullable|string|max:40'
        ]);
        $user = $request->user();
        if (!$user->withinDeviceLimit($data['device_uuid'])) {
            return response()->json(['error' => 'device_limit_reached'], 409);
        }
        $device = $user->devices()->updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            array_merge($data, ['last_ip' => $request->ip(), 'last_seen_at' => now()])
        );
        return response()->json(['device' => $device]);
    }

    public function heartbeat(Request $request)
    {
        $request->validate(['device_uuid' => 'required']);
        $device = $request->user()->devices()->where('device_uuid',$request->device_uuid)->first();
        if ($device) {
            $device->update(['last_seen_at' => now(), 'last_ip' => $request->ip()]);
        }
        return response()->json(['ok' => true]);
    }

    public function revokeDevice(Request $request, $uuid)
    {
        $device = $request->user()->devices()->where('device_uuid',$uuid)->first();
        if ($device) $device->delete();
        return response()->json(['removed' => (bool)$device]);
    }

    public function requestDownload(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'nullable|integer',
            'tts_audio_product_id' => 'nullable|integer',
            'device_uuid' => 'nullable|string',
            'format' => 'nullable|string|in:pdf,mobile'
        ]);
        $user = $request->user();
        $productId = $data['product_id'] ?? null;
        $ttsAudioProductId = $data['tts_audio_product_id'] ?? null;
        $format = $data['format'] ?? 'pdf';
        if (!$productId && !$ttsAudioProductId) {
            return response()->json(['error'=>'missing_target'],422);
        }
        $disk = null; $filePath = null; $downloadName = null; $size = null; $sha256 = null;
        if ($productId) {
            $product = Product::find($productId);
            if ($product) {
                if (!$product->canUserAccessFull($user)) return response()->json(['error'=>'no_access'],403);
                [$disk, $filePath, $downloadName] = $this->resolveProductDownload($product, $format);
            } else {
                // Compatibility for app releases that submitted a TTS library
                // item ID in product_id instead of tts_audio_product_id.
                $tts = TtsAudioProduct::active()->findOrFail($productId);
                if (!$user->hasTtsProductAccess($tts->id) && !$user->hasActiveSubscription()) return response()->json(['error'=>'no_access'],403);
                [$disk, $filePath, $downloadName] = $this->resolveTtsDownload($tts, $format);
                $ttsAudioProductId = $tts->id;
                $productId = null;
            }
        } else {
            $tts = TtsAudioProduct::active()->findOrFail($ttsAudioProductId);
            if (!$user->hasTtsProductAccess($tts->id) && !$user->hasActiveSubscription()) return response()->json(['error'=>'no_access'],403);
            [$disk, $filePath, $downloadName] = $this->resolveTtsDownload($tts, $format);
        }
        if (!$filePath) return response()->json(['error'=>'file_missing'],404);
        $abs = Storage::disk($disk)->path($filePath);
        if (is_file($abs)) {
            $size = filesize($abs);
            $sha256 = hash_file('sha256',$abs);
        }
        $download = $user->downloads()->create([
            'product_id' => $productId,
            'tts_audio_product_id' => $ttsAudioProductId,
            'device_uuid' => $data['device_uuid'] ?? null,
            'bytes' => $size,
            'sha256' => $sha256,
            'completed' => false
        ]);
        $expires = now()->addMinutes(10);
        $signedUrl = URL::temporarySignedRoute('secure.download', $expires, [
            'download' => $download->id,
            'format' => $format,
        ]);
        return response()->json([
            'download_id' => $download->id,
            'url' => $signedUrl,
            'download_url' => $signedUrl,
            'signed_url' => $signedUrl,
            'expires_at' => $expires->toIso8601String(),
            'type' => 'pdf',
            'format' => $format,
            'filename' => $downloadName,
            'bytes' => $size,
            'sha256' => $sha256
        ]);
    }

    private function resolveProductDownload(Product $product, string $format = 'pdf'): array
    {
        // Mobile-optimized PDF requested: prefer the dedicated mobile file,
        // falling back to the standard PDF when only one exists.
        if ($format === 'mobile') {
            if ($product->mobile_pdf_file_path) {
                return [
                    $this->diskContaining($product->mobile_pdf_file_path),
                    $product->mobile_pdf_file_path,
                    ($product->slug ?: 'product-' . $product->id) . '-mobile.pdf',
                ];
            }
        }

        if (!$product->pdf_file_path) {
            return [null, null, null];
        }

        return [
            $this->diskContaining($product->pdf_file_path),
            $product->pdf_file_path,
            ($product->slug ?: 'product-' . $product->id) . '.pdf',
        ];
    }

    private function resolveTtsDownload(TtsAudioProduct $product, string $format = 'pdf'): array
    {
        if ($format === 'mobile') {
            if ($product->mobile_pdf_file_path) {
                return [
                    $this->diskContaining($product->mobile_pdf_file_path),
                    $product->mobile_pdf_file_path,
                    ($product->slug ?: 'tts-product-' . $product->id) . '-mobile.pdf',
                ];
            }
        }

        if (!$product->pdf_file_path) {
            return [null, null, null];
        }

        return [
            $this->diskContaining($product->pdf_file_path),
            $product->pdf_file_path,
            ($product->slug ?: 'tts-product-' . $product->id) . '.pdf',
        ];
    }

    /**
     * Pick the disk that actually holds the file. Licensed book PDFs live on
     * the (non-web) "private" disk so they can only be served through the
     * authenticated signed-URL flow; admin-uploaded PDFs remain on the
     * "public" disk, so we fall back to it.
     */
    private function diskContaining(string $path): string
    {
        if (Storage::disk('private')->exists($path)) {
            return 'private';
        }
        return 'public';
    }

    public function completeDownload(Request $request)
    {
        $data = $request->validate([
            'download_id' => 'required|integer',
            'bytes' => 'nullable|integer',
            'sha256' => 'nullable|string',
            'device_uuid' => 'nullable|string'
        ]);
        $download = $request->user()->downloads()->findOrFail($data['download_id']);
        $download->update([
            'completed' => true,
            'completed_at' => now(),
            'bytes' => $data['bytes'] ?? $download->bytes,
            'sha256' => $data['sha256'] ?? $download->sha256,
            'device_uuid' => $data['device_uuid'] ?? $download->device_uuid
        ]);
        return response()->json(['ok'=>true]);
    }

    public function pruneDownloads(Request $request)
    {
        $request->validate([
            'older_than_minutes' => 'nullable|integer|min:10',
            'incomplete_only' => 'nullable|boolean'
        ]);
        $user = $request->user();
        $query = $user->downloads();
        if($request->boolean('incomplete_only', false)){
            $query->where('completed', false);
        }
        if($minutes = $request->integer('older_than_minutes')){
            $query->where('created_at','<', now()->subMinutes($minutes));
        }
        $ids = $query->pluck('id');
        $count = 0;
        if($ids->count()){
            $count = $user->downloads()->whereIn('id',$ids)->delete();
        }
        return response()->json(['deleted'=>$count]);
    }
}
