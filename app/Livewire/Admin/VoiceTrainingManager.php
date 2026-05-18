<?php

namespace App\Livewire\Admin;

use App\Services\XttsVastAiService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;

class VoiceTrainingManager extends Component
{
    use WithFileUploads;

    // ── State ────────────────────────────────────────────────────────────
    public string $activeTab = 'speakers';   // speakers | train | jobs

    // Speaker management
    public string $newSpeakerName = '';
    public $speakerFile = null;
    public string $speakerStatus = '';

    // Test synthesis
    public string $testText = 'I am grateful for this moment. Every day I grow stronger and more confident.';
    public string $testSpeaker = '';
    public string $testLanguage = 'en';
    public string $testAudioB64 = '';
    public string $testStatus = '';

    // Training data
    public string $trainVoiceName = '';
    public string $trainTranscript = '';
    public $trainFile = null;
    public string $trainUploadStatus = '';
    public array $trainSamples = [];
    public int $trainEpochs = 100;
    public int $trainBatchSize = 2;
    public string $trainJobStatus = '';

    // Jobs
    public array $jobs = [];
    public string $pollingJobId = '';

    // Loaded data
    public array $speakers = [];
    public bool $serviceOnline = false;

    // ── Lifecycle ────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->refreshSpeakers();
    }

    public function refreshSpeakers(): void
    {
        $svc = $this->svc();
        if (!$svc->isConfigured()) {
            $this->serviceOnline = false;
            return;
        }
        $health = $svc->health();
        $this->serviceOnline = ($health['status'] ?? '') === 'ok';
        if ($this->serviceOnline) {
            $this->speakers = $svc->listSpeakers();
        }
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->speakerStatus = '';
        $this->trainUploadStatus = '';
        $this->trainJobStatus = '';
        $this->testStatus = '';
        $this->testAudioB64 = '';

        if ($tab === 'jobs') {
            $this->refreshJobs();
        } elseif ($tab === 'train' && $this->trainVoiceName) {
            $this->loadTrainSamples();
        }
    }

    // ── Speaker Management ───────────────────────────────────────────────
    public function uploadSpeaker(): void
    {
        $this->validate([
            'newSpeakerName' => 'required|alpha_dash|min:2|max:40',
            'speakerFile'    => 'required|file|max:20480|mimes:wav,mp3,m4a,ogg,webm',
        ]);

        try {
            $path = $this->speakerFile->getRealPath();
            $result = $this->svc()->uploadSpeaker($this->newSpeakerName, $path);
            $this->speakerStatus = 'success:Speaker "' . ($result['name'] ?? $this->newSpeakerName) . '" uploaded (' . ($result['size_kb'] ?? '?') . ' KB)';
            $this->newSpeakerName = '';
            $this->speakerFile = null;
            $this->refreshSpeakers();
        } catch (\Exception $e) {
            $this->speakerStatus = 'error:' . $this->extractError($e);
            Log::error('[XTTS] Speaker upload error', ['error' => $e->getMessage()]);
        }
    }

    public function deleteSpeaker(string $name): void
    {
        try {
            $this->svc()->deleteSpeaker($name);
            $this->speakerStatus = 'success:Speaker "' . $name . '" deleted';
            $this->refreshSpeakers();
            if ($this->testSpeaker === $name) {
                $this->testSpeaker = '';
            }
        } catch (\Exception $e) {
            $this->speakerStatus = 'error:' . $this->extractError($e);
        }
    }

    // ── Test Synthesis ───────────────────────────────────────────────────
    public function testSynthesize(): void
    {
        if (empty(trim($this->testText))) {
            $this->testStatus = 'error:Please enter some text to synthesize';
            return;
        }

        try {
            $this->testAudioB64 = '';
            $wavBytes = $this->svc()->synthesize($this->testText, $this->testSpeaker, $this->testLanguage);
            $this->testAudioB64 = base64_encode($wavBytes);
            $this->testStatus = 'success:Audio generated (' . round(strlen($wavBytes) / 1024) . ' KB)';
        } catch (\Exception $e) {
            $this->testStatus = 'error:' . $this->extractError($e);
            Log::error('[XTTS] Synthesis error', ['error' => $e->getMessage()]);
        }
    }

    // ── Training Data ────────────────────────────────────────────────────
    public function updatedTrainVoiceName(): void
    {
        if ($this->trainVoiceName) {
            $this->loadTrainSamples();
        } else {
            $this->trainSamples = [];
        }
    }

    public function loadTrainSamples(): void
    {
        if (!$this->trainVoiceName) return;
        try {
            $result = $this->svc()->listTrainingSamples($this->trainVoiceName);
            $this->trainSamples = $result['samples'] ?? [];
        } catch (\Exception $e) {
            $this->trainSamples = [];
        }
    }

    public function uploadTrainingSample(): void
    {
        $this->validate([
            'trainVoiceName'  => 'required|alpha_dash|min:2|max:40',
            'trainTranscript' => 'required|string|min:3|max:500',
            'trainFile'       => 'required|file|max:20480|mimes:wav,mp3,m4a,ogg,webm',
        ]);

        try {
            $path = $this->trainFile->getRealPath();
            $result = $this->svc()->uploadTrainingSample($this->trainVoiceName, $path, $this->trainTranscript);
            $this->trainUploadStatus = 'success:Sample uploaded. Total: ' . ($result['sample_count'] ?? '?') . ' samples';
            $this->trainTranscript = '';
            $this->trainFile = null;
            $this->loadTrainSamples();
        } catch (\Exception $e) {
            $this->trainUploadStatus = 'error:' . $this->extractError($e);
            Log::error('[XTTS] Training sample upload error', ['error' => $e->getMessage()]);
        }
    }

    public function deleteTrainingSample(string $sampleId): void
    {
        try {
            $this->svc()->deleteTrainingSample($this->trainVoiceName, $sampleId);
            $this->loadTrainSamples();
        } catch (\Exception $e) {
            $this->trainUploadStatus = 'error:' . $this->extractError($e);
        }
    }

    public function startTraining(): void
    {
        if (empty($this->trainVoiceName)) {
            $this->trainJobStatus = 'error:Voice name is required';
            return;
        }
        if (count($this->trainSamples) < 5) {
            $this->trainJobStatus = 'error:Need at least 5 audio samples to start training. Have: ' . count($this->trainSamples);
            return;
        }

        try {
            $result = $this->svc()->startTraining($this->trainVoiceName, $this->trainEpochs, $this->trainBatchSize);
            $this->pollingJobId = $result['job_id'] ?? '';
            $this->trainJobStatus = 'success:Training started! Job ID: ' . $this->pollingJobId;
            $this->activeTab = 'jobs';
            $this->refreshJobs();
        } catch (\Exception $e) {
            $this->trainJobStatus = 'error:' . $this->extractError($e);
            Log::error('[XTTS] Training start error', ['error' => $e->getMessage()]);
        }
    }

    // ── Jobs ─────────────────────────────────────────────────────────────
    public function refreshJobs(): void
    {
        try {
            $this->jobs = $this->svc()->listJobs();
        } catch (\Exception $e) {
            $this->jobs = [];
        }
    }

    public function cancelJob(string $jobId): void
    {
        try {
            $this->svc()->cancelJob($jobId);
            $this->refreshJobs();
        } catch (\Exception $e) {
            // silently update status
            $this->refreshJobs();
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    private function svc(): XttsVastAiService
    {
        return app(XttsVastAiService::class);
    }

    private function extractError(\Exception $e): string
    {
        $msg = $e->getMessage();
        // Try to get JSON error detail from HTTP response
        if (method_exists($e, 'response') && $e->response()) {
            $detail = $e->response()->json('detail');
            if ($detail) return $detail;
        }
        return \Illuminate\Support\Str::limit($msg, 120);
    }

    public function render()
    {
        return view('livewire.admin.voice-training-manager')
            ->layout('components.layouts.admin');
    }
}
