<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TTSIntegrationService
{
    private $ttsBaseUrl;
    private $ttsApiKey;

    public function __construct()
    {
        $this->ttsBaseUrl = config('services.tts.base_url', 'https://meditative-brains.com:3001/api');
        $this->ttsApiKey = config('services.tts.api_key');
    }

    /**
     * Get TTS categories from Node.js backend
     */
    public function getCategories()
    {
        try {
            $response = Http::timeout(30)->get($this->ttsBaseUrl . '/category');
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('TTS API Error: Failed to fetch categories', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return [];
        } catch (\Exception $e) {
            Log::error('TTS API Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get motivation messages by category
     */
    public function getMotivationMessages($categoryId)
    {
        try {
            $response = Http::timeout(30)->get($this->ttsBaseUrl . '/motivationMessage/category/' . $categoryId);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('TTS API Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate TTS audio via Node.js backend
     */
    public function generateTTSAudio($params)
    {
        try {
            $response = Http::timeout(120)
                ->post($this->ttsBaseUrl . '/attentionGuide/audio', [
                    'text' => $params['text'],
                    'speaker' => $params['speaker'] ?? 'en-US-AriaNeural',
                    'language' => $params['language'] ?? 'en-US',
                    'category' => $params['category'] ?? 'motivation',
                    'prosodyRate' => $params['prosodyRate'] ?? 'medium',
                    'prosodyPitch' => $params['prosodyPitch'] ?? 'medium',
                    'prosodyVolume' => $params['prosodyVolume'] ?? 'medium',
                    'engine' => $params['engine'] ?? 'azure'
                ]);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('TTS Generation Error', [
                'params' => $params,
                'response' => $response->body()
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error('TTS Generation Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Import TTS audio as Laravel product
     */
    public function importTTSAsProduct($ttsData, $categoryId)
    {
        try {
            // Download TTS audio file
            $audioUrl = $this->ttsBaseUrl . $ttsData['audioUrl'];
            $audioContent = Http::get($audioUrl)->body();
            
            if (empty($audioContent)) {
                throw new \Exception('Failed to download TTS audio');
            }
            
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'tts_import_');
            file_put_contents($tempFile, $audioContent);
            
            // Use AudioSecurityService to encrypt and store
            $audioService = app(AudioSecurityService::class);
            $filename = sanitize_filename($ttsData['text']) . '.mp3';
            $encryptedPath = $audioService->encryptAndStore($tempFile, $filename);
            
            // Clean up temp file
            unlink($tempFile);
            
            return $encryptedPath;
            
        } catch (\Exception $e) {
            Log::error('TTS Import Error: ' . $e->getMessage());
            throw $e;
        }
    }
}

/**
 * Helper function to sanitize filename
 */
if (!function_exists('sanitize_filename')) {
    function sanitize_filename($filename, $maxLength = 50) {
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
        return substr($filename, 0, $maxLength);
    }
}
