<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class AudioGenerator extends Component
{
    public $category = '';
    public $language = 'en-US';
    public $speaker = 'en-US-AriaNeural';
    public $engine = 'azure';
    public $messages = '';
    public $prosodyRate = 'medium';
    public $prosodyPitch = 'medium';
    public $prosodyVolume = 'medium';
    public $backgroundMusic = false;
    public $musicVolume = 0.3;

    public $languages = [
        'en-US', 'en-GB', 'en-AU', 'en-CA', 'en-IN',
        'es-ES', 'es-MX', 'fr-FR', 'fr-CA', 'de-DE',
        'it-IT', 'pt-BR', 'pt-PT', 'ja-JP', 'ko-KR',
        'zh-CN', 'zh-TW', 'ru-RU', 'ar-EG', 'hi-IN'
    ];

    public $speakersByLanguage = [
        'en-US' => [
            'en-US-AriaNeural', 'en-US-JennyNeural', 'en-US-GuyNeural',
            'en-US-DavisNeural', 'en-US-AmberNeural', 'en-US-AnaNeural'
        ],
        'en-GB' => [
            'en-GB-SoniaNeural', 'en-GB-RyanNeural', 'en-GB-LibbyNeural'
        ],
        'es-ES' => [
            'es-ES-ElviraNeural', 'es-ES-AlvaroNeural'
        ],
        'fr-FR' => [
            'fr-FR-DeniseNeural', 'fr-FR-HenriNeural'
        ],
        'de-DE' => [
            'de-DE-KatjaNeural', 'de-DE-ConradNeural'
        ]
    ];

    public $isGenerating = false;
    public $generationResult = null;

    protected $rules = [
        'category' => 'required|string|min:2',
        'messages' => 'required|string|min:10',
        'language' => 'required|string',
        'speaker' => 'required|string',
        'engine' => 'required|in:azure,vits'
    ];

    public function mount()
    {
        $this->updateSpeakers();
    }

    public function updatedLanguage()
    {
        $this->updateSpeakers();
    }

    private function updateSpeakers()
    {
        $speakers = $this->speakersByLanguage[$this->language] ?? [];
        if (!empty($speakers)) {
            $this->speaker = $speakers[0];
        }
    }

    public function generateAudio()
    {
        $this->validate();

        $this->isGenerating = true;
        $this->generationResult = null;

        try {
            $payload = [
                'text' => $this->messages,
                'category' => $this->category,
                'language' => $this->language,
                'speaker' => $this->speaker,
                'engine' => $this->engine,
                'prosodyRate' => $this->prosodyRate,
                'prosodyPitch' => $this->prosodyPitch,
                'prosodyVolume' => $this->prosodyVolume,
                'backgroundMusic' => $this->backgroundMusic,
                'musicVolume' => $this->musicVolume
            ];

            $response = Http::timeout(180)->post('https://meditative-brains.com:3001/api/attentionGuide/audio', $payload);

            if ($response->successful()) {
                $this->generationResult = $response->json();
                session()->flash('success', 'Audio generated successfully!');
            } else {
                \Log::error('Audio generation failed', [
                    'payload' => $payload,
                    'response' => $response->body()
                ]);
                session()->flash('error', 'Audio generation failed: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('Audio generation exception: ' . $e->getMessage());
            session()->flash('error', 'Error generating audio: ' . $e->getMessage());
        } finally {
            $this->isGenerating = false;
        }
    }

    public function resetForm()
    {
        $this->reset([
            'category', 'messages', 'prosodyRate', 'prosodyPitch', 'prosodyVolume',
            'backgroundMusic', 'musicVolume', 'generationResult'
        ]);
        $this->language = 'en-US';
        $this->speaker = 'en-US-AriaNeural';
        $this->engine = 'azure';
        $this->updateSpeakers();
    }

    public function downloadAudio()
    {
        if (!$this->generationResult || !isset($this->generationResult['audioUrl'])) {
            session()->flash('error', 'No audio file available for download');
            return;
        }

        // Return a download response
        $audioUrl = 'https://meditative-brains.com:3001' . $this->generationResult['audioUrl'];
        return redirect()->to($audioUrl);
    }

    public function render()
    {
        return view('livewire.admin.audio-generator')
            ->extends('adminlte::page')
            ->section('content');
    }
}
