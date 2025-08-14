<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AudioService
{
    protected $ttsBackendUrl;
    protected $defaultTimeout;

    public function __construct()
    {
        $this->ttsBackendUrl = 'https://meditative-brains.com:3001';
        $this->defaultTimeout = 60; // 60 seconds for audio generation
    }

    /**
     * Generate preview audio with background music
     */
    public function generatePreview(array $options)
    {
        $defaultOptions = [
            'voice' => 'default',
            'speed' => 1.0,
            'language' => 'en',
            'preview_duration' => 30,
            'background_music_url' => null
        ];

        $options = array_merge($defaultOptions, $options);

        // Validate required fields
        if (empty($options['text'])) {
            throw new \InvalidArgumentException('Text is required for preview generation');
        }

        if (strlen($options['text']) > 500) {
            throw new \InvalidArgumentException('Text too long for preview (max 500 characters)');
        }

        // Create cache key for this specific preview
        $cacheKey = 'preview_' . md5(json_encode($options));
        
        // Check if we have a cached preview
        $cachedPreview = Cache::get($cacheKey);
        if ($cachedPreview) {
            Log::info('Returning cached preview', ['cache_key' => $cacheKey]);
            return $cachedPreview;
        }

        try {
            Log::info('Generating new preview', [
                'text_length' => strlen($options['text']),
                'voice' => $options['voice'],
                'has_background_music' => !empty($options['background_music_url'])
            ]);

            $response = Http::timeout($this->defaultTimeout)
                ->post($this->ttsBackendUrl . '/api/generate-preview', [
                    'text' => $options['text'],
                    'voice' => $options['voice'],
                    'speed' => $options['speed'],
                    'language' => $options['language'],
                    'preview_duration' => $options['preview_duration'],
                    'background_music_url' => $options['background_music_url']
                ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Cache the result for 30 minutes
                Cache::put($cacheKey, $result, 30 * 60);
                
                Log::info('Preview generated successfully', [
                    'duration' => $result['duration'] ?? 'unknown',
                    'has_url' => !empty($result['preview_url'])
                ]);

                return $result;
            }

            throw new \Exception('TTS backend returned error: ' . $response->status());

        } catch (\Exception $e) {
            Log::error('Preview generation failed', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);

            throw new \Exception('Failed to generate preview: ' . $e->getMessage());
        }
    }

    /**
     * Generate full audio (for purchased content)
     */
    public function generateFullAudio(array $options)
    {
        $defaultOptions = [
            'voice' => 'default',
            'speed' => 1.0,
            'language' => 'en',
            'background_music_url' => null,
            'audio_quality' => 'high'
        ];

        $options = array_merge($defaultOptions, $options);

        try {
            Log::info('Generating full audio', [
                'text_length' => strlen($options['text']),
                'voice' => $options['voice'],
                'quality' => $options['audio_quality']
            ]);

            $response = Http::timeout(120) // Longer timeout for full audio
                ->post($this->ttsBackendUrl . '/api/generate-audio', [
                    'text' => $options['text'],
                    'voice' => $options['voice'],
                    'speed' => $options['speed'],
                    'language' => $options['language'],
                    'background_music_url' => $options['background_music_url'],
                    'quality' => $options['audio_quality']
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('TTS backend returned error: ' . $response->status());

        } catch (\Exception $e) {
            Log::error('Full audio generation failed', [
                'error' => $e->getMessage(),
                'options' => $options
            ]);

            throw new \Exception('Failed to generate audio: ' . $e->getMessage());
        }
    }

    /**
     * Get available voices from TTS backend
     */
    public function getAvailableVoices($language = 'en')
    {
        $cacheKey = "voices_{$language}";
        
        return Cache::remember($cacheKey, 3600, function () use ($language) {
            try {
                $response = Http::timeout(10)
                    ->get($this->ttsBackendUrl . '/api/voices', [
                        'language' => $language
                    ]);

                if ($response->successful()) {
                    return $response->json();
                }

                // Return default voices if backend is unavailable
                return $this->getDefaultVoices($language);

            } catch (\Exception $e) {
                Log::warning('Failed to fetch voices from backend', [
                    'error' => $e->getMessage(),
                    'language' => $language
                ]);

                return $this->getDefaultVoices($language);
            }
        });
    }

    /**
     * Get default voices as fallback
     */
    protected function getDefaultVoices($language = 'en')
    {
        $defaultVoices = [
            'en' => [
                ['id' => 'en-US-male-1', 'name' => 'David (Male)', 'gender' => 'male'],
                ['id' => 'en-US-female-1', 'name' => 'Sarah (Female)', 'gender' => 'female'],
                ['id' => 'en-US-male-2', 'name' => 'Michael (Male)', 'gender' => 'male'],
                ['id' => 'en-US-female-2', 'name' => 'Emma (Female)', 'gender' => 'female'],
            ],
            'es' => [
                ['id' => 'es-ES-male-1', 'name' => 'Carlos (Male)', 'gender' => 'male'],
                ['id' => 'es-ES-female-1', 'name' => 'Maria (Female)', 'gender' => 'female'],
            ],
            'fr' => [
                ['id' => 'fr-FR-male-1', 'name' => 'Pierre (Male)', 'gender' => 'male'],
                ['id' => 'fr-FR-female-1', 'name' => 'Marie (Female)', 'gender' => 'female'],
            ]
        ];

        return [
            'voices' => $defaultVoices[$language] ?? $defaultVoices['en'],
            'language' => $language,
            'source' => 'fallback'
        ];
    }

    /**
     * Validate preview request parameters
     */
    public function validatePreviewRequest(array $data)
    {
        $rules = [
            'text' => 'required|string|max:500',
            'voice' => 'sometimes|string|max:50',
            'speed' => 'sometimes|numeric|min:0.5|max:2.0',
            'language' => 'sometimes|string|size:2',
            'preview_duration' => 'sometimes|integer|min:10|max:60'
        ];

        $errors = [];

        foreach ($rules as $field => $rule) {
            $rulesParts = explode('|', $rule);
            
            foreach ($rulesParts as $rulePart) {
                if ($rulePart === 'required' && empty($data[$field])) {
                    $errors[$field] = "The {$field} field is required.";
                    break;
                }
                
                if (str_starts_with($rulePart, 'max:') && isset($data[$field])) {
                    $max = (int) substr($rulePart, 4);
                    if (is_string($data[$field]) && strlen($data[$field]) > $max) {
                        $errors[$field] = "The {$field} may not be greater than {$max} characters.";
                    }
                    if (is_numeric($data[$field]) && $data[$field] > $max) {
                        $errors[$field] = "The {$field} may not be greater than {$max}.";
                    }
                }
                
                if (str_starts_with($rulePart, 'min:') && isset($data[$field])) {
                    $min = (float) substr($rulePart, 4);
                    if (is_numeric($data[$field]) && $data[$field] < $min) {
                        $errors[$field] = "The {$field} may not be less than {$min}.";
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . json_encode($errors));
        }

        return true;
    }

    /**
     * Get audio generation statistics
     */
    public function getGenerationStats()
    {
        // This could be expanded to track usage statistics
        return [
            'backend_url' => $this->ttsBackendUrl,
            'timeout' => $this->defaultTimeout,
            'cache_enabled' => true,
            'status' => 'operational'
        ];
    }
}
