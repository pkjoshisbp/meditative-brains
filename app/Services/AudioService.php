<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\TtsAudioGeneratorService;

class AudioService
{
    protected $defaultTimeout = 60;

    /**
     * Generate preview audio via TtsAudioGeneratorService (Azure / VITS).
     */
    public function generatePreview(array $options): array
    {
        $options = array_merge([
            'voice'                => 'en-US-AvaMultilingualNeural',
            'speed'                => 1.0,
            'language'             => 'en-US',
            'preview_duration'     => 30,
            'background_music_url' => null,
        ], $options);

        if (empty($options['text'])) {
            throw new \InvalidArgumentException('Text is required for preview generation');
        }

        if (strlen($options['text']) > 500) {
            throw new \InvalidArgumentException('Text too long for preview (max 500 characters)');
        }

        $cacheKey = 'preview_' . md5(json_encode($options));
        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        try {
            /** @var TtsAudioGeneratorService $generator */
            $generator = app(TtsAudioGeneratorService::class);
            $result = $generator->generateForMessage($options['text'], [
                'language' => $options['language'],
                'speaker'  => $options['voice'],
                'engine'   => 'azure',
                'category' => 'preview',
            ]);

            if (empty($result['audioUrl'])) {
                throw new \Exception('Audio generation returned no URL');
            }

            $output = ['preview_url' => $result['audioUrl'], 'audioUrl' => $result['audioUrl']];
            Cache::put($cacheKey, $output, 1800);
            return $output;
        } catch (\Exception $e) {
            Log::error('Preview generation failed: ' . $e->getMessage());
            throw new \Exception('Failed to generate preview: ' . $e->getMessage());
        }
    }

    /**
     * Generate full audio via TtsAudioGeneratorService.
     */
    public function generateFullAudio(array $options): array
    {
        $options = array_merge([
            'voice'                => 'en-US-AvaMultilingualNeural',
            'speed'                => 1.0,
            'language'             => 'en-US',
            'background_music_url' => null,
            'audio_quality'        => 'high',
        ], $options);

        try {
            /** @var TtsAudioGeneratorService $generator */
            $generator = app(TtsAudioGeneratorService::class);
            $result = $generator->generateForMessage($options['text'], [
                'language' => $options['language'],
                'speaker'  => $options['voice'],
                'engine'   => 'azure',
                'category' => 'full-audio',
            ]);

            if (empty($result['audioUrl'])) {
                throw new \Exception('Audio generation returned no URL');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Full audio generation failed: ' . $e->getMessage());
            throw new \Exception('Failed to generate audio: ' . $e->getMessage());
        }
    }

    /**
     * Get available voices — read from Azure voices JSON config.
     */
    public function getAvailableVoices(string $language = 'en'): array
    {
        $cacheKey = "voices_{$language}";
        return Cache::remember($cacheKey, 3600, function () use ($language) {
            $voices = json_decode(file_get_contents(config_path('azure-voices.json')), true) ?? [];
            $filtered = array_values(array_filter($voices, fn($v) =>
                str_starts_with(strtolower($v['Locale'] ?? ''), strtolower($language))
            ));
            return ['voices' => $filtered, 'language' => $language, 'source' => 'azure'];
        });
    }

    /**
     * Get audio generation statistics.
     */
    public function getGenerationStats(): array
    {
        return [
            'backend' => 'Laravel/Azure (no Node dependency)',
            'timeout' => $this->defaultTimeout,
            'cache_enabled' => true,
            'status' => 'operational',
        ];
    }
}
