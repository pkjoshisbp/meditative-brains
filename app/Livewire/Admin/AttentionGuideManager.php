<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Models\TtsAttentionGuide;
use App\Services\TtsAudioGeneratorService;

class AttentionGuideManager extends Component
{
    // ── Form fields ─────────────────────────────────────────────────
    public string  $text        = '';
    public string  $language    = 'en-US';
    public string  $speaker     = 'en-US-AvaMultilingualNeural';
    public string  $engine      = 'azure';
    public string  $speed       = 'medium';
    public int     $intervalSec = 60;   // interval in seconds for the UI
    public bool    $isActive    = true;

    // ── Edit state ──────────────────────────────────────────────────
    public ?int $editingId = null;

    // ── Voice data ──────────────────────────────────────────────────
    public array $languages = [];
    public array $speakers  = [];

    // ── Records ─────────────────────────────────────────────────────
    public array $guides = [];

    // ── Validation ──────────────────────────────────────────────────
    protected function rules(): array
    {
        return [
            'text'        => 'required|string|min:2|max:1000',
            'language'    => 'required|string',
            'speaker'     => 'required|string',
            'engine'      => 'required|in:azure,openai',
            'speed'       => 'required|string',
            'intervalSec' => 'required|integer|min:5|max:86400',
            'isActive'    => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Lifecycle
    // ─────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->loadVoices();
        $this->loadGuides();
    }

    // ─────────────────────────────────────────────────────────────────
    // Voice helpers
    // ─────────────────────────────────────────────────────────────────

    private function loadVoices(): void
    {
        $path = config_path('azure-voices.json');
        if (!file_exists($path)) {
            $this->languages = ['en-US'];
            $this->speakers  = ['en-US-AvaMultilingualNeural'];
            return;
        }
        $voices          = collect(json_decode(file_get_contents($path), true) ?? []);
        $this->languages = $voices->pluck('Locale')->unique()->sort()->values()->toArray();
        $this->refreshSpeakers($voices);
    }

    private function refreshSpeakers(?Collection $voices = null): void
    {
        if ($voices === null) {
            $path = config_path('azure-voices.json');
            if (!file_exists($path)) {
                return;
            }
            $voices = collect(json_decode(file_get_contents($path), true) ?? []);
        }

        $lang = $this->language;
        $filtered = $voices->filter(fn($v) =>
            ($v['Locale'] ?? '') === $lang ||
            in_array($lang, $v['SecondaryLocaleList'] ?? [])
        );

        $this->speakers = $filtered->pluck('ShortName')->values()->toArray();

        if (!in_array($this->speaker, $this->speakers) && !empty($this->speakers)) {
            $this->speaker = $this->speakers[0];
        }
    }

    public function updatedLanguage(): void
    {
        $this->refreshSpeakers();
    }

    // ─────────────────────────────────────────────────────────────────
    // Data
    // ─────────────────────────────────────────────────────────────────

    private function loadGuides(): void
    {
        $this->guides = TtsAttentionGuide::orderBy('id')->get()->toArray();
    }

    // ─────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate();

        $data = [
            'text'        => trim($this->text),
            'language'    => $this->language,
            'speaker'     => $this->speaker,
            'engine'      => $this->engine,
            'speed'       => $this->speed,
            'interval_ms' => $this->intervalSec * 1000,
            'is_active'   => $this->isActive,
        ];

        if ($this->editingId) {
            TtsAttentionGuide::where('id', $this->editingId)->update($data);
            $guide = TtsAttentionGuide::find($this->editingId);
        } else {
            $guide = TtsAttentionGuide::create($data);
        }

        $this->generateAudio($guide);

        $this->resetForm();
        $this->loadGuides();
    }

    private function generateAudio(TtsAttentionGuide $guide): void
    {
        try {
            /** @var TtsAudioGeneratorService $generator */
            $generator = app(TtsAudioGeneratorService::class);

            $result = $generator->generateForMessage($guide->text, [
                'engine'      => $guide->engine ?? 'azure',
                'language'    => $guide->language,
                'speaker'     => $guide->speaker,
                'prosodyRate' => $guide->speed ?? 'medium',
                'category'    => 'attention-guide',
            ]);

            if (!empty($result['audioUrl'])) {
                $guide->update([
                    'audio_url'  => $result['audioUrl'],
                    'audio_path' => $result['relativePath'] ?? null,
                ]);
                session()->flash('success', 'Attention guide saved and audio generated successfully.');
            } else {
                Log::warning('Attention guide audio generation returned no URL', ['guide_id' => $guide->id]);
                session()->flash('warning', 'Guide saved, but audio generation returned no URL.');
            }
        } catch (\Exception $e) {
            Log::error('Attention guide audio generation exception: ' . $e->getMessage());
            session()->flash('warning', 'Guide saved, but audio generation error: ' . $e->getMessage());
        }
    }

    public function edit(int $id): void
    {
        $guide = TtsAttentionGuide::findOrFail($id);
        $this->editingId   = $id;
        $this->text        = $guide->text;
        $this->language    = $guide->language;
        $this->speaker     = $guide->speaker;
        $this->engine      = $guide->engine;
        $this->speed       = $guide->speed;
        $this->intervalSec = intdiv($guide->interval_ms, 1000);
        $this->isActive    = (bool) $guide->is_active;
        $this->refreshSpeakers();
    }

    public function delete(int $id): void
    {
        TtsAttentionGuide::where('id', $id)->delete();
        session()->flash('success', 'Attention guide deleted.');
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->loadGuides();
    }

    public function toggleActive(int $id): void
    {
        $guide = TtsAttentionGuide::findOrFail($id);
        $guide->is_active = !$guide->is_active;
        $guide->save();
        $this->loadGuides();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId   = null;
        $this->text        = '';
        $this->language    = 'en-US';
        $this->speaker     = 'en-US-AvaMultilingualNeural';
        $this->engine      = 'azure';
        $this->speed       = 'medium';
        $this->intervalSec = 60;
        $this->isActive    = true;
        $this->refreshSpeakers();
        $this->resetValidation();
    }

    // ─────────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.admin.attention-guide-manager')
            ->layout('layouts.admin');
    }
}
