<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TtsAudioProduct;
use App\Models\Product;

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
            'device_uuid' => 'nullable|string'
        ]);
        $user = $request->user();
        if (!$data['product_id'] && !$data['tts_audio_product_id']) {
            return response()->json(['error'=>'missing_target'],422);
        }
        $filePath = null; $size = null; $sha256 = null;
        if ($data['product_id']) {
            $product = Product::findOrFail($data['product_id']);
            if (!$product->canUserAccessFull($user)) return response()->json(['error'=>'no_access'],403);
            $filePath = $product->full_file;
        } else {
            $tts = TtsAudioProduct::active()->findOrFail($data['tts_audio_product_id']);
            if (!$user->hasTtsProductAccess($tts->id) && !$user->hasActiveSubscription()) return response()->json(['error'=>'no_access'],403);
            $filePath = $tts->audio_urls[0] ?? null;
        }
        if (!$filePath) return response()->json(['error'=>'file_missing'],404);
        $abs = storage_path('app/'.$filePath);
        if (is_file($abs)) {
            $size = filesize($abs);
            $sha256 = hash_file('sha256',$abs);
        }
        $download = $user->downloads()->create([
            'product_id' => $data['product_id'],
            'tts_audio_product_id' => $data['tts_audio_product_id'],
            'device_uuid' => $data['device_uuid'] ?? null,
            'bytes' => $size,
            'sha256' => $sha256,
            'completed' => false
        ]);
        $temporaryUrl = route('secure.download', ['id' => $download->id, 'token' => sha1($download->id.config('app.key').now()->format('YmdHi'))]);
        return response()->json(['download_id' => $download->id,'url'=>$temporaryUrl,'bytes'=>$size,'sha256'=>$sha256]);
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
}
