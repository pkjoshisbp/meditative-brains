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
     * Encrypt and store audio file with optimized encryption
     */
    public function encryptAndStore($audioFile, $filename = null)
    {
        if (!$filename) {
            // Generate filename based on original file
            $originalName = basename($audioFile);
            $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
            $filename = $nameWithoutExt . '.enc';
        }
        
        // Read the original audio file
        $audioContent = file_get_contents($audioFile);
        
        // Use OpenSSL for more efficient encryption (binary output)
        $key = hash('sha256', config('app.key'), true);
        $iv = openssl_random_pseudo_bytes(16);
        $encryptedContent = openssl_encrypt($audioContent, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        // Prepend IV to encrypted content for decryption
        $finalContent = $iv . $encryptedContent;
        
        // Store in private storage
        $path = 'encrypted-audio/' . $filename;
        Storage::disk('local')->put($path, $finalContent);
        
        return $path;
    }

    /**
     * Decrypt audio file content with optimized decryption
     */
    public function decryptAudio($encryptedPath)
    {
        if (!Storage::disk('local')->exists($encryptedPath)) {
            throw new \Exception('Encrypted audio file not found');
        }

        $encryptedContent = Storage::disk('local')->get($encryptedPath);
        
        // Extract IV and encrypted data
        $iv = substr($encryptedContent, 0, 16);
        $encryptedData = substr($encryptedContent, 16);
        
        // Decrypt using OpenSSL
        $key = hash('sha256', config('app.key'), true);
        $decryptedContent = openssl_decrypt($encryptedData, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decryptedContent === false) {
            throw new \Exception('Failed to decrypt audio file');
        }
        
        return $decryptedContent;
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
        
        // Get the absolute filesystem path
        $absolutePath = storage_path('app/' . $fullPath);
        
        if (!is_dir($absolutePath)) {
            return [
                'files' => [], 
                'directories' => [],
                'current_path' => $directory
            ];
        }

        try {
            $files = [];
            $directories = [];
            
            // Use PHP's scandir for more reliable results
            $items = scandir($absolutePath);
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $itemPath = $absolutePath . '/' . $item;
                $relativePath = $directory ? $directory . '/' . $item : $item;
                
                if (is_dir($itemPath)) {
                    $directories[] = [
                        'name' => $item,
                        'path' => $relativePath  // This should be relative to original folder
                    ];
                } elseif (is_file($itemPath)) {
                    $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (in_array($extension, ['mp3', 'wav', 'm4a', 'flac', 'aac', 'ogg'])) {
                        $files[] = [
                            'name' => $item,
                            'path' => $relativePath,
                            'size' => filesize($itemPath),
                            'modified' => filemtime($itemPath)
                        ];
                    }
                }
            }

            return [
                'files' => $files,
                'directories' => $directories,
                'current_path' => $directory
            ];
        } catch (\Exception $e) {
            \Log::error('AudioSecurityService error: ' . $e->getMessage());
            return [
                'files' => [], 
                'directories' => [],
                'current_path' => $directory
            ];
        }
    }

    /**
     * Encrypt and store an original audio file for a product
     */
    public function encryptOriginalFile($originalPath, $productId)
    {
        // Build the full path to the original file
        $fullOriginalPath = 'audio/original/' . ltrim($originalPath, '/');
        
        if (!Storage::disk('local')->exists($fullOriginalPath)) {
            throw new \Exception("Original file not found: {$originalPath}");
        }

        // Generate encrypted filename based on original filename
        $originalName = basename($originalPath);
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        $encryptedFileName = $nameWithoutExt . '.enc';
        $encryptedPath = 'audio/encrypted/' . $encryptedFileName;

        // Read original file
        $originalContent = Storage::disk('local')->get($fullOriginalPath);
        
        // Use OpenSSL for more efficient encryption (binary output)
        $key = hash('sha256', config('app.key'), true);
        $iv = openssl_random_pseudo_bytes(16);
        $encryptedContent = openssl_encrypt($originalContent, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        
        // Prepend IV to encrypted content for decryption
        $finalContent = $iv . $encryptedContent;
        
        // Store encrypted file
        Storage::disk('local')->put($encryptedPath, $finalContent);
        
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

    /**
     * Encrypt a background music original file located under bg-music/original
     * Returns encrypted path bg-music/encrypted/<name>.enc
     */
    public function encryptBgMusicFile($filename)
    {
        $filename = ltrim($filename, '/');
        // Accept either filename.mp3 or sub/dir/filename.mp3
        $originalFull = 'bg-music/original/' . $filename;
        if (!Storage::disk('local')->exists($originalFull)) {
            throw new \Exception("Background music original not found: {$filename}");
        }
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $encryptedPath = 'bg-music/encrypted/' . $baseName . '.enc';
        if (Storage::disk('local')->exists($encryptedPath)) {
            return $encryptedPath; // already encrypted
        }
        $originalContent = Storage::disk('local')->get($originalFull);
        $key = hash('sha256', config('app.key'), true);
        $iv = openssl_random_pseudo_bytes(16);
        $encryptedContent = openssl_encrypt($originalContent, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $finalContent = $iv . $encryptedContent;
        Storage::disk('local')->put($encryptedPath, $finalContent);
        return $encryptedPath;
    }
}
