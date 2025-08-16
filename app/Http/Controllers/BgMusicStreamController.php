<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AudioSecurityService;

class BgMusicStreamController extends Controller
{
    public function __construct(private AudioSecurityService $audioSecurityService) {}

    // Issue a temporary signed URL (token based) for a background music track name (without extension)
    public function issue(Request $request)
    {
        $request->validate([
            'track' => 'required|string'
        ]);
        $track = $request->get('track');
        try {
            \Log::info('BG issue request', ['track'=>$track]);
            // Resolve actual filename (case-insensitive match in directory)
            $dir = storage_path('app/bg-music/original');
            $candidate = null;
            if (is_dir($dir)) {
                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $base = pathinfo($item, PATHINFO_FILENAME);
                    if (strcasecmp($base, $track) === 0) {
                        $candidate = $item; // includes extension
                        break;
                    }
                }
            }
            if (!$candidate) {
                // fallback assume mp3
                $candidate = $track . '.mp3';
            }
            $this->audioSecurityService->encryptBgMusicFile($candidate);
            $encryptedPath = 'bg-music/encrypted/' . pathinfo($candidate, PATHINFO_FILENAME) . '.enc';
            $url = $this->audioSecurityService->generateSecureUrl($encryptedPath, null, 30); // 30 min
            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            \Log::warning('BG issue failed', ['track'=>$track,'error'=>$e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
}
