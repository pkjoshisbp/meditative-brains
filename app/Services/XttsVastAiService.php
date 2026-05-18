<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XttsVastAiService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.vastai_xtts.url', ''), '/');
        $this->timeout = 120;
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl);
    }

    public function health(): array
    {
        try {
            $resp = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $resp->json() ?? ['status' => 'error'];
        } catch (\Exception $e) {
            return ['status' => 'unreachable', 'error' => $e->getMessage()];
        }
    }

    public function listSpeakers(): array
    {
        $resp = Http::timeout(10)->get("{$this->baseUrl}/speakers");
        $resp->throw();
        return $resp->json('speakers', []);
    }

    /**
     * Upload a speaker reference WAV file for zero-shot voice cloning.
     *
     * @param string $name  Speaker name (alphanumeric + underscores)
     * @param string $filePath  Absolute path to the audio file
     * @return array  Response from the API
     */
    public function uploadSpeaker(string $name, string $filePath): array
    {
        $resp = Http::timeout(60)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post("{$this->baseUrl}/speakers/upload", ['name' => $name]);
        $resp->throw();
        return $resp->json();
    }

    public function deleteSpeaker(string $name): array
    {
        $resp = Http::timeout(10)->delete("{$this->baseUrl}/speakers/{$name}");
        $resp->throw();
        return $resp->json();
    }

    /**
     * Synthesize speech using XTTS with optional speaker cloning.
     *
     * @return string  Raw WAV audio bytes
     */
    public function synthesize(string $text, string $speaker = '', string $language = 'en'): string
    {
        $resp = Http::timeout($this->timeout)->post("{$this->baseUrl}/tts", [
            'text'     => $text,
            'speaker'  => $speaker,
            'language' => $language,
        ]);
        $resp->throw();

        $b64 = $resp->json('audio_base64');
        if (empty($b64)) {
            throw new \RuntimeException('XTTS synthesis returned no audio data');
        }
        return base64_decode($b64);
    }

    // ── Training data management ─────────────────────────────────────────

    public function uploadTrainingSample(string $voiceName, string $filePath, string $transcript): array
    {
        $resp = Http::timeout(60)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post("{$this->baseUrl}/train/upload-sample", [
                'voice_name' => $voiceName,
                'transcript' => $transcript,
            ]);
        $resp->throw();
        return $resp->json();
    }

    public function listTrainingSamples(string $voiceName): array
    {
        $resp = Http::timeout(10)->get("{$this->baseUrl}/train/samples/{$voiceName}");
        $resp->throw();
        return $resp->json();
    }

    public function deleteTrainingSample(string $voiceName, string $sampleId): array
    {
        $resp = Http::timeout(10)->delete("{$this->baseUrl}/train/samples/{$voiceName}/{$sampleId}");
        $resp->throw();
        return $resp->json();
    }

    // ── Fine-tuning jobs ─────────────────────────────────────────────────

    public function startTraining(string $voiceName, int $epochs = 100, int $batchSize = 2): array
    {
        $resp = Http::timeout(15)->post("{$this->baseUrl}/train/start", [
            'voice_name' => $voiceName,
            'epochs'     => $epochs,
            'batch_size' => $batchSize,
        ]);
        $resp->throw();
        return $resp->json();
    }

    public function listJobs(): array
    {
        $resp = Http::timeout(10)->get("{$this->baseUrl}/train/jobs");
        $resp->throw();
        return $resp->json('jobs', []);
    }

    public function getJob(string $jobId): array
    {
        $resp = Http::timeout(10)->get("{$this->baseUrl}/train/jobs/{$jobId}");
        $resp->throw();
        return $resp->json();
    }

    public function cancelJob(string $jobId): array
    {
        $resp = Http::timeout(10)->post("{$this->baseUrl}/train/cancel/{$jobId}");
        $resp->throw();
        return $resp->json();
    }
}
