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
            // ensure encrypted exists (mp3 by default)
            $this->audioSecurityService->encryptBgMusicFile($track . '.mp3');
            $encryptedPath = 'bg-music/encrypted/' . $track . '.enc';
            $url = $this->audioSecurityService->generateSecureUrl($encryptedPath, null, 30); // 30 min
            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
}
