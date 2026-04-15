<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AudioSecurityService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Show the admin BG music management page.
     * Lists existing tracks and provides an upload form.
     */
    public function adminIndex()
    {
        $originalDir  = storage_path('app/bg-music/original');
        $encryptedDir = storage_path('app/bg-music/encrypted');
        $publicDir    = public_path('bg-music');

        $tracks = [];
        if (is_dir($originalDir)) {
            foreach (scandir($originalDir) as $f) {
                if ($f === '.' || $f === '..') continue;
                if (!preg_match('/\.(mp3|aac|m4a|wav|ogg)$/i', $f)) continue;
                $slug    = pathinfo($f, PATHINFO_FILENAME);
                $tracks[] = [
                    'filename'       => $f,
                    'slug'           => $slug,
                    'size_kb'        => round(filesize($originalDir . '/' . $f) / 1024),
                    'has_encrypted'  => file_exists($encryptedDir . '/' . $slug . '.enc'),
                    'has_public'     => file_exists($publicDir . '/' . $f),
                ];
            }
        }

        return view('admin.tts.bg-music', compact('tracks'));
    }

    /**
     * Handle upload of a new background music track.
     * Stores to:  storage/app/bg-music/original/<slug>.<ext>
     *             public/bg-music/<slug>.<ext>
     * Then auto-encrypts to: storage/app/bg-music/encrypted/<slug>.enc
     */
    public function adminUpload(Request $request)
    {
        $request->validate([
            'audio_file' => 'required|file|mimes:mp3,aac,m4a,wav,ogg|max:30720',
            'track_name' => 'required|string|max:100',
        ]);

        $name    = Str::slug($request->input('track_name'));
        $file    = $request->file('audio_file');
        $ext     = strtolower($file->getClientOriginalExtension());
        $filename = $name . '.' . $ext;

        // Security: prevent traversal
        if ($filename !== basename($filename) || str_contains($filename, '..')) {
            return back()->withErrors(['audio_file' => 'Invalid filename.']);
        }

        // Store original
        $originalPath = storage_path('app/bg-music/original');
        if (!is_dir($originalPath)) {
            mkdir($originalPath, 0755, true);
        }
        $file->move($originalPath, $filename);

        // Copy to public/bg-music for home-screen playback (no auth required)
        $publicPath = public_path('bg-music');
        if (!is_dir($publicPath)) {
            mkdir($publicPath, 0755, true);
        }
        copy($originalPath . '/' . $filename, $publicPath . '/' . $filename);

        // Encrypt for secure Flutter streaming
        $encryptError = null;
        try {
            $this->audioSecurityService->encryptBgMusicFile($filename);
        } catch (\Throwable $e) {
            $encryptError = $e->getMessage();
            \Log::error('[Admin] BG music encryption failed', ['file' => $filename, 'err' => $encryptError]);
        }

        \Log::info('[Admin] BG music uploaded', ['filename' => $filename]);

        $msg = "Track \"$filename\" uploaded successfully.";
        if ($encryptError) {
            $msg .= " (Encryption failed: $encryptError — will retry on next list request.)";
        }

        return back()->with('success', $msg);
    }

    /**
     * Delete a background music track (original, public copy, and encrypted).
     */
    public function adminDelete(Request $request)
    {
        $request->validate(['filename' => 'required|string']);
        $filename = basename($request->input('filename')); // prevent traversal

        $slug = pathinfo($filename, PATHINFO_FILENAME);

        Storage::disk('local')->delete('bg-music/original/' . $filename);
        Storage::disk('local')->delete('bg-music/encrypted/' . $slug . '.enc');

        $publicFile = public_path('bg-music/' . $filename);
        if (file_exists($publicFile)) {
            unlink($publicFile);
        }

        \Log::info('[Admin] BG music deleted', ['filename' => $filename]);

        return back()->with('success', "Track \"$filename\" deleted.");
    }
}
