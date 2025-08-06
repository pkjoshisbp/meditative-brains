<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AudioSecurityService
{
    private $encryptionKey;
    
    public function __construct()
    {
        $this->encryptionKey = config('app.key');
    }

    /**
     * Encrypt and store audio file
     */
    public function encryptAndStore($audioFile, $filename = null)
    {
        $filename = $filename ?: Str::random(40) . '.enc';
        
        // Read the original audio file
        $audioContent = file_get_contents($audioFile);
        
        // Encrypt the content
        $encryptedContent = Crypt::encrypt($audioContent);
        
        // Store in private storage
        $path = 'encrypted-audio/' . $filename;
        Storage::disk('local')->put($path, $encryptedContent);
        
        return $path;
    }

    /**
     * Decrypt audio file content
     */
    public function decryptAudio($encryptedPath)
    {
        if (!Storage::disk('local')->exists($encryptedPath)) {
            throw new \Exception('Encrypted audio file not found');
        }

        $encryptedContent = Storage::disk('local')->get($encryptedPath);
        return Crypt::decrypt($encryptedContent);
    }

    /**
     * Create a preview of specified length
     */
    public function createPreview($encryptedPath, $previewLengthSeconds = 30)
    {
        $audioContent = $this->decryptAudio($encryptedPath);
        
        // For MP3 files, we'll use a simple approach to create previews
        // In production, you'd want to use FFmpeg for precise audio manipulation
        return $this->truncateAudioContent($audioContent, $previewLengthSeconds);
    }

    /**
     * Generate a secure temporary URL for audio streaming
     */
    public function generateSecureUrl($encryptedPath, $previewLength = null, $expiresInMinutes = 60)
    {
        $token = Str::random(64);
        $expires = now()->addMinutes($expiresInMinutes)->timestamp;
        
        // Store the token with file info in cache
        cache()->put("audio_token_{$token}", [
            'path' => $encryptedPath,
            'preview_length' => $previewLength,
            'expires' => $expires,
        ], $expiresInMinutes);

        return route('audio.stream', [
            'token' => $token,
            'expires' => $expires,
            'signature' => hash_hmac('sha256', $token . $expires, $this->encryptionKey)
        ]);
    }

    /**
     * Validate and retrieve audio data from token
     */
    public function getAudioFromToken($token, $expires, $signature)
    {
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $token . $expires, $this->encryptionKey);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new \Exception('Invalid signature');
        }

        // Check expiration
        if (time() > $expires) {
            throw new \Exception('Link expired');
        }

        // Get audio info from cache
        $audioInfo = cache()->get("audio_token_{$token}");
        if (!$audioInfo) {
            throw new \Exception('Invalid or expired token');
        }

        // Decrypt and return audio
        if ($audioInfo['preview_length']) {
            return $this->createPreview($audioInfo['path'], $audioInfo['preview_length']);
        }

        return $this->decryptAudio($audioInfo['path']);
    }

    /**
     * Simple audio truncation (for demonstration)
     * In production, use FFmpeg for proper audio manipulation
     */
    private function truncateAudioContent($audioContent, $lengthSeconds)
    {
        // This is a simplified approach - in production, use FFmpeg
        // For now, we'll calculate approximate bytes based on typical MP3 bitrates
        $approximateBytesPerSecond = 16000; // Rough estimate for 128kbps MP3
        $maxBytes = $lengthSeconds * $approximateBytesPerSecond;
        
        if (strlen($audioContent) > $maxBytes) {
            return substr($audioContent, 0, $maxBytes);
        }
        
        return $audioContent;
    }

    /**
     * Generate a secure signed URL for audio streaming (Laravel signed URLs)
     */
    public function generateSignedUrl($encryptedPath, $previewLength = null, $expiresInMinutes = 60)
    {
        $parameters = [
            'path' => base64_encode($encryptedPath),
            'preview' => $previewLength,
        ];

        return URL::temporarySignedRoute(
            'audio.signed-stream',
            now()->addMinutes($expiresInMinutes),
            $parameters
        );
    }

    /**
     * Validate and stream audio from signed URL
     */
    public function streamFromSignedUrl($encodedPath, $previewLength = null)
    {
        $path = base64_decode($encodedPath);
        
        if (!Storage::disk('local')->exists($path)) {
            throw new \Exception('Audio file not found');
        }

        if ($previewLength) {
            return $this->createPreview($path, $previewLength);
        }

        return $this->decryptAudio($path);
    }
    public function getAudioMetadata($encryptedPath)
    {
        // Read just the first part of the file to get metadata
        $encryptedContent = Storage::disk('local')->get($encryptedPath);
        $audioContent = Crypt::decrypt($encryptedContent);
        
        // Extract basic metadata (simplified)
        return [
            'size' => strlen($audioContent),
            'estimated_duration' => $this->estimateDuration($audioContent),
            'format' => $this->detectAudioFormat($audioContent)
        ];
    }

    private function estimateDuration($audioContent)
    {
        // Simplified duration estimation based on file size and typical bitrates
        $approximateBytesPerSecond = 16000; // 128kbps MP3
        return round(strlen($audioContent) / $approximateBytesPerSecond);
    }

    private function detectAudioFormat($audioContent)
    {
        $header = substr($audioContent, 0, 10);
        
        if (substr($header, 0, 3) === 'ID3' || substr($header, 0, 2) === "\xFF\xFB") {
            return 'mp3';
        } elseif (substr($header, 0, 4) === 'RIFF') {
            return 'wav';
        } elseif (substr($header, 0, 4) === 'OggS') {
            return 'ogg';
        }
        
        return 'unknown';
    }

    /**
     * Get list of available audio files from original storage
     */
    public function getAvailableAudioFiles($directory = '')
    {
        $basePath = 'audio/original';
        $fullPath = $directory ? $basePath . '/' . trim($directory, '/') : $basePath;
        
        if (!Storage::disk('local')->exists($fullPath)) {
            return ['files' => [], 'directories' => []];
        }

        $contents = Storage::disk('local')->listContents($fullPath);
        $files = [];
        $directories = [];

        foreach ($contents as $item) {
            if ($item['type'] === 'file') {
                $extension = strtolower(pathinfo($item['path'], PATHINFO_EXTENSION));
                if (in_array($extension, ['mp3', 'wav', 'm4a', 'flac', 'aac', 'ogg'])) {
                    $files[] = [
                        'name' => basename($item['path']),
                        'path' => $item['path'],
                        'size' => $item['size'] ?? 0,
                        'modified' => $item['lastModified'] ?? null
                    ];
                }
            } elseif ($item['type'] === 'dir') {
                $directories[] = [
                    'name' => basename($item['path']),
                    'path' => $item['path']
                ];
            }
        }

        return [
            'files' => $files,
            'directories' => $directories,
            'current_path' => $directory
        ];
    }

    /**
     * Encrypt and store an original audio file for a product
     */
    public function encryptOriginalFile($originalPath, $productId)
    {
        if (!Storage::disk('local')->exists($originalPath)) {
            throw new \Exception("Original file not found: {$originalPath}");
        }

        // Generate unique encrypted filename
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
        $encryptedFileName = 'product_' . $productId . '_' . Str::random(16) . '.enc';
        $encryptedPath = 'audio/encrypted/' . $encryptedFileName;

        // Read original file
        $originalContent = Storage::disk('local')->get($originalPath);
        
        // Encrypt the content
        $encryptedContent = Crypt::encrypt($originalContent);
        
        // Store encrypted file
        Storage::disk('local')->put($encryptedPath, $encryptedContent);
        
        // Return the encrypted path for storage in database
        return $encryptedPath;
    }

    /**
     * Upload audio file to original storage
     */
    public function uploadOriginalFile($uploadedFile, $directory = '')
    {
        $basePath = 'audio/original';
        $targetPath = $directory ? $basePath . '/' . trim($directory, '/') : $basePath;
        
        // Generate safe filename
        $filename = time() . '_' . Str::slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $uploadedFile->getClientOriginalExtension();
        
        // Store the file
        $path = $uploadedFile->storeAs($targetPath, $filename, 'local');
        
        return $path;
    }
}
