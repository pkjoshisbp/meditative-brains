<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\TtsSourceCategory;
use App\Models\TtsMotivationMessage;
use App\Services\TtsAudioGeneratorService;

class TTSIntegrationService
{
    /**
     * Get TTS categories from MySQL.
     */
    public function getCategories(): array
    {
        return TtsSourceCategory::orderBy('category')
            ->get()
            ->map(fn($c) => ['_id' => (string)$c->id, 'mongo_id' => $c->mongo_id, 'category' => $c->category])
            ->toArray();
    }

    /**
     * Get motivation messages by category ID (mongo_id or MySQL id).
     */
    public function getMotivationMessages($categoryId): array
    {
        $cat = TtsSourceCategory::where('mongo_id', $categoryId)
            ->orWhere('id', is_numeric($categoryId) ? $categoryId : 0)
            ->first();

        if (!$cat) return [];

        return TtsMotivationMessage::where('source_category_id', $cat->id)
            ->get()
            ->map(fn($m) => [
                '_id'       => (string)$m->id,
                'messages'  => $m->messages ?? [],
                'language'  => $m->language,
                'speaker'   => $m->speaker,
                'engine'    => $m->engine,
                'audioUrls' => $m->audio_urls ?? [],
            ])
            ->toArray();
    }

    /**
     * Generate TTS audio via TtsAudioGeneratorService (pure PHP/Azure).
     */
    public function generateTTSAudio(array $params): ?array
    {
        try {
            /** @var TtsAudioGeneratorService $generator */
            $generator = app(TtsAudioGeneratorService::class);
            $result = $generator->generateForMessage($params['text'], [
                'engine'       => $params['engine']       ?? 'azure',
                'language'     => $params['language']     ?? 'en-US',
                'speaker'      => $params['speaker']      ?? 'en-US-AvaMultilingualNeural',
                'category'     => $params['category']     ?? 'motivation',
                'prosodyRate'  => $params['prosodyRate']  ?? 'medium',
                'prosodyPitch' => $params['prosodyPitch'] ?? 'medium',
                'prosodyVolume'=> $params['prosodyVolume']?? 'medium',
            ]);
            return $result ?: null;
        } catch (\Exception $e) {
            Log::error('TTSIntegrationService::generateTTSAudio exception: ' . $e->getMessage());
            return null;
        }
    }
}
