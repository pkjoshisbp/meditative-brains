<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class AudioBookGenerator extends Component
{
    // Book metadata
    public string $bookTitle  = 'Practicing Happiness';
    public string $bookAuthor = 'Pawan Joshi';

    /**
     * All chapters. Each item:
     *   id           int
     *   title        string
     *   plain_content string
     *   ssml_content  string
     *   status        pending|generating|done|error
     *   audio_url     string|null
     *   error         string|null
     */
    public array $chapters = [];

    // Which chapter is open in the editor
    public ?int $activeChapterId = null;

    // Editor tab: 'plain' or 'ssml'
    public string $activeTab = 'ssml';

    // ── Voice settings ──────────────────────────────────────────────
    public string $engine      = 'azure';
    public string $language    = 'en-IN';
    public string $speaker     = 'en-GB-AdaMultilingualNeural';
    public string $speakerStyle = '';
    public string $prosodyRate  = 'medium';
    public string $prosodyPitch = 'medium';
    public string $prosodyVolume = 'medium';

    // ── Runtime state ────────────────────────────────────────────────
    public ?int   $generatingChapterId = null;
    public string $importStatus        = '';   // "success:msg" or "error:msg"
    public ?string $savedBookId        = null; // MongoDB _id if saved

    // ── Voice data ───────────────────────────────────────────────────
    public array $languages               = [];
    public array $speakers                = [];
    public array $availableStyles         = [];
    public string $expressionStyle        = '';
    public array $availableExpressionStyles = [];
    public array $savedBooks              = []; // list for load dropdown

    private string $backendUrl = '';

    // ─────────────────────────────────────────────────────────────────
    // Lifecycle
    // ─────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->backendUrl = rtrim(config('services.tts.base_url'), '/api');
        $this->loadVoices();
        $this->loadSavedBooksList();
        $this->chapters = [[
            'id'            => 1,
            'title'         => 'Introduction',
            'plain_content' => '',
            'ssml_content'  => '',
            'status'        => 'pending',
            'audio_url'     => null,
            'error'         => null,
        ]];
        $this->activeChapterId = 1;
    }

    private function loadSavedBooksList(): void
    {
        try {
            $response = Http::timeout(10)->get("{$this->backendUrl}/api/audiobook");
            if ($response->successful()) {
                $this->savedBooks = $response->json('books') ?? [];
            }
        } catch (\Exception $e) {
            // Silently fail — list is non-critical
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Voice helpers
    // ─────────────────────────────────────────────────────────────────

    private function loadVoices(): void
    {
        $path = config_path('azure-voices.json');
        if (!file_exists($path)) {
            return;
        }
        $voices = collect(json_decode(file_get_contents($path), true) ?? []);
        $this->languages = $voices->pluck('Locale')->unique()->sort()->values()->toArray();
        $this->refreshSpeakers($voices);
    }

    private function refreshSpeakers(?Collection $voices = null): void
    {
        if ($voices === null) {
            $path = config_path('azure-voices.json');
            if (!file_exists($path)) return;
            $voices = collect(json_decode(file_get_contents($path), true) ?? []);
        }

        $lang = $this->language;

        // Include voices whose primary locale matches OR whose SecondaryLocaleList contains the language
        $filtered = $voices->filter(function ($v) use ($lang) {
            if (($v['Locale'] ?? '') === $lang) return true;
            return in_array($lang, $v['SecondaryLocaleList'] ?? []);
        });

        $this->speakers = $filtered->pluck('ShortName')->values()->toArray();

        if (!in_array($this->speaker, $this->speakers) && !empty($this->speakers)) {
            $this->speaker = $this->speakers[0];
        }

        $voice = $filtered->firstWhere('ShortName', $this->speaker);
        $this->availableStyles = $voice['StyleList'] ?? [];
        if (!in_array($this->speakerStyle, $this->availableStyles)) {
            $this->speakerStyle = '';
        }
        $this->availableExpressionStyles = $voice['VoiceTag']['TailoredScenarios'] ?? [];
        if (!in_array($this->expressionStyle, $this->availableExpressionStyles)) {
            $this->expressionStyle = $this->availableExpressionStyles[0] ?? '';
        }
    }

    public function updatedLanguage(): void
    {
        $this->refreshSpeakers();
    }

    public function updatedSpeaker(): void
    {
        $path = config_path('azure-voices.json');
        if (!file_exists($path)) return;
        $voices = collect(json_decode(file_get_contents($path), true) ?? []);
        $lang   = $this->language;
        $voices = $voices->filter(fn($v) =>
            ($v['Locale'] ?? '') === $lang ||
            in_array($lang, $v['SecondaryLocaleList'] ?? [])
        );
        $voice  = $voices->firstWhere('ShortName', $this->speaker);
        $this->availableStyles = $voice['StyleList'] ?? [];
        if (!in_array($this->speakerStyle, $this->availableStyles)) {
            $this->speakerStyle = '';
        }
        $this->availableExpressionStyles = $voice['VoiceTag']['TailoredScenarios'] ?? [];
        if (!in_array($this->expressionStyle, $this->availableExpressionStyles)) {
            $this->expressionStyle = $this->availableExpressionStyles[0] ?? '';
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Chapter management
    // ─────────────────────────────────────────────────────────────────

    public function setActiveChapter(int $id): void
    {
        $this->activeChapterId = $id;
        $this->activeTab       = 'ssml';
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function addChapter(): void
    {
        $maxId = collect($this->chapters)->max('id') ?? 0;
        $count = count($this->chapters) + 1;
        $this->chapters[] = [
            'id'            => $maxId + 1,
            'title'         => "Chapter {$count}",
            'plain_content' => '',
            'ssml_content'  => '',
            'status'        => 'pending',
            'audio_url'     => null,
            'error'         => null,
        ];
        $this->activeChapterId = $maxId + 1;
    }

    public function removeChapter(int $id): void
    {
        if (count($this->chapters) <= 1) return;
        $this->chapters = collect($this->chapters)
            ->filter(fn($c) => $c['id'] !== $id)
            ->values()
            ->toArray();
        if ($this->activeChapterId === $id) {
            $this->activeChapterId = $this->chapters[0]['id'] ?? null;
        }
    }

    public function moveUp(int $id): void
    {
        $i = collect($this->chapters)->search(fn($c) => $c['id'] === $id);
        if ($i > 0) {
            [$this->chapters[$i - 1], $this->chapters[$i]] =
                [$this->chapters[$i], $this->chapters[$i - 1]];
        }
    }

    public function moveDown(int $id): void
    {
        $i = collect($this->chapters)->search(fn($c) => $c['id'] === $id);
        if ($i !== false && $i < count($this->chapters) - 1) {
            [$this->chapters[$i + 1], $this->chapters[$i]] =
                [$this->chapters[$i], $this->chapters[$i + 1]];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Import from practicing-happiness/tts/
    // ─────────────────────────────────────────────────────────────────

    public function importFromFiles(): void
    {
        $ttsDir = base_path('practicing-happiness/tts');

        if (!is_dir($ttsDir)) {
            $this->importStatus = 'error:TTS directory not found at practicing-happiness/tts/';
            return;
        }

        $files = collect(scandir($ttsDir))
            ->filter(fn($f) => str_ends_with($f, '.txt'))
            ->sort()
            ->values();

        if ($files->isEmpty()) {
            $this->importStatus = 'error:No .txt files found in practicing-happiness/tts/';
            return;
        }

        $this->chapters = [];
        foreach ($files as $i => $file) {
            $content = file_get_contents("{$ttsDir}/{$file}");
            $slug    = preg_replace('/^\d+-/', '', pathinfo($file, PATHINFO_FILENAME));
            $title   = ucwords(str_replace('-', ' ', $slug));

            $this->chapters[] = [
                'id'            => $i + 1,
                'title'         => $title,
                'plain_content' => $this->stripMarkup($content),
                'ssml_content'  => $content,
                'status'        => 'pending',
                'audio_url'     => null,
                'error'         => null,
            ];
        }

        $this->activeChapterId = $this->chapters[0]['id'] ?? null;
        $count = count($this->chapters);
        $this->importStatus = "success:{$count} chapters imported from practicing-happiness/tts/";
    }

    private function stripMarkup(string $text): string
    {
        $s = preg_replace('/\[pause:\d+\]|\[silence:\d+\]/i', '', $text);
        $s = preg_replace('/\[personality:[^\]]*\]|\[\/personality\]/i', '', $s);
        $s = preg_replace('/\[rate:[^\]]*\]|\[\/rate\]/i', '', $s);
        $s = preg_replace('/\[[^\]]*\]/i', '', $s);
        $s = strip_tags($s);
        $s = preg_replace('/\*\*|__|[*_]/', '', $s);

        // Preserve paragraph structure (blank lines) but clean up inline whitespace
        $lines = explode("\n", $s);
        $lines = array_map(fn($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), $lines);
        return implode("\n", $lines);
    }

    // ─────────────────────────────────────────────────────────────────
    // Audio generation
    // ─────────────────────────────────────────────────────────────────

    public function generateChapter(int $id): void
    {
        $index = collect($this->chapters)->search(fn($c) => $c['id'] === $id);
        if ($index === false) return;

        $chapter = $this->chapters[$index];
        $content = !empty(trim($chapter['ssml_content']))
            ? $chapter['ssml_content']
            : $chapter['plain_content'];

        if (empty(trim($content))) {
            $this->chapters[$index]['status'] = 'error';
            $this->chapters[$index]['error']  = 'No content to generate audio from.';
            return;
        }

        $this->chapters[$index]['status'] = 'generating';
        $this->chapters[$index]['error']  = null;
        $this->generatingChapterId        = $id;

        try {
            $response = Http::timeout(300)->post("{$this->backendUrl}/api/tts/preview", [
                'text'         => $content,
                'voice'        => $this->speaker,
                'engine'       => $this->engine,
                'language'     => $this->language,
                'prosodyRate'  => $this->prosodyRate,
                'prosodyPitch' => $this->prosodyPitch,
                'prosodyVolume'=> $this->prosodyVolume,
                'speakerStyle'    => $this->speakerStyle ?: null,
                'expressionStyle' => $this->expressionStyle ?: null,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->chapters[$index]['status']    = 'done';
                $this->chapters[$index]['audio_url'] = $data['preview']['audio_url'] ?? null;
                $this->chapters[$index]['error']     = null;
            } else {
                $this->chapters[$index]['status'] = 'error';
                $this->chapters[$index]['error']  = 'Server responded with HTTP ' . $response->status();
                \Log::error('AudioBook chapter generation failed', [
                    'chapter_id' => $id,
                    'status'     => $response->status(),
                    'response'   => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            $this->chapters[$index]['status'] = 'error';
            $this->chapters[$index]['error']  = $e->getMessage();
            \Log::error('AudioBook chapter generation exception', ['message' => $e->getMessage()]);
        } finally {
            $this->generatingChapterId = null;
        }
    }

    /**
     * Generate audio for every pending/errored chapter in sequence.
     * Note: this runs in a single PHP request — suitable for admin use.
     */
    public function generateAll(): void
    {
        foreach ($this->chapters as $chapter) {
            if (in_array($chapter['status'], ['pending', 'error'])) {
                $this->generateChapter($chapter['id']);
            }
        }
    }

    public function resetChapter(int $id): void
    {
        $index = collect($this->chapters)->search(fn($c) => $c['id'] === $id);
        if ($index !== false) {
            $this->chapters[$index]['status']    = 'pending';
            $this->chapters[$index]['audio_url'] = null;
            $this->chapters[$index]['error']     = null;
        }
    }

    public function resetAllGenerated(): void
    {
        $this->chapters = collect($this->chapters)->map(function ($c) {
            $c['status']    = 'pending';
            $c['audio_url'] = null;
            $c['error']     = null;
            return $c;
        })->toArray();
    }

    // ─────────────────────────────────────────────────────────────────
    // Save / Load
    // ─────────────────────────────────────────────────────────────────

    public function saveBook(): void
    {
        if (empty(trim($this->bookTitle))) {
            $this->importStatus = 'error:Book title is required to save.';
            return;
        }

        $payload = [
            'bookTitle'      => $this->bookTitle,
            'bookAuthor'     => $this->bookAuthor,
            'language'       => $this->language,
            'speaker'        => $this->speaker,
            'engine'         => $this->engine,
            'speakerStyle'   => $this->speakerStyle ?: null,
            'expressionStyle'=> $this->expressionStyle ?: null,
            'prosodyRate'    => $this->prosodyRate,
            'prosodyPitch'   => $this->prosodyPitch,
            'prosodyVolume'  => $this->prosodyVolume,
            'chapters'       => collect($this->chapters)->map(fn($ch, $i) => [
                'chapterNumber' => $i + 1,
                'title'         => $ch['title'],
                'plainContent'  => $ch['plain_content'],
                'ssmlContent'   => $ch['ssml_content'],
                'audioPath'     => '',
                'audioUrl'      => $ch['audio_url'] ?? '',
                'status'        => $ch['status'],
            ])->toArray(),
        ];

        try {
            $response = Http::timeout(30)->post("{$this->backendUrl}/api/audiobook", $payload);
            if ($response->successful()) {
                $this->savedBookId = $response->json('book._id') ?? null;
                $this->loadSavedBooksList();
                $this->importStatus = 'success:Book saved successfully!';
            } else {
                $this->importStatus = 'error:Save failed: ' . $response->body();
            }
        } catch (\Exception $e) {
            $this->importStatus = 'error:Save error: ' . $e->getMessage();
        }
    }

    public function loadBook(string $bookId): void
    {
        try {
            $response = Http::timeout(15)->get("{$this->backendUrl}/api/audiobook/{$bookId}");
            if (!$response->successful()) {
                $this->importStatus = 'error:Could not load book.';
                return;
            }
            $book = $response->json('book');
            if (!$book) {
                $this->importStatus = 'error:Book data is empty.';
                return;
            }

            $this->savedBookId  = $book['_id'] ?? null;
            $this->bookTitle    = $book['bookTitle'] ?? '';
            $this->bookAuthor   = $book['bookAuthor'] ?? '';
            $this->language     = $book['language'] ?? 'en-US';
            $this->speaker      = $book['speaker'] ?? 'en-US-AriaNeural';
            $this->engine       = $book['engine'] ?? 'azure';
            $this->speakerStyle = $book['speakerStyle'] ?? '';
            $this->expressionStyle = $book['expressionStyle'] ?? '';
            $this->prosodyRate  = $book['prosodyRate'] ?? 'medium';
            $this->prosodyPitch = $book['prosodyPitch'] ?? 'medium';
            $this->prosodyVolume= $book['prosodyVolume'] ?? 'medium';

            $this->chapters = collect($book['chapters'] ?? [])
                ->sortBy('chapterNumber')
                ->values()
                ->map(fn($ch, $i) => [
                    'id'            => $i + 1,
                    'title'         => $ch['title'] ?? '',
                    'plain_content' => $ch['plainContent'] ?? '',
                    'ssml_content'  => $ch['ssmlContent'] ?? '',
                    'status'        => ($ch['status'] === 'done' && !empty($ch['audioUrl'])) ? 'done' : 'pending',
                    'audio_url'     => $ch['audioUrl'] ?? null,
                    'error'         => null,
                ])
                ->toArray();

            $this->activeChapterId = $this->chapters[0]['id'] ?? null;
            $this->refreshSpeakers();
            $count = count($this->chapters);
            $this->importStatus = "success:Loaded \"{$this->bookTitle}\" with {$count} chapters.";
        } catch (\Exception $e) {
            $this->importStatus = 'error:Load error: ' . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────────

    public function render()
    {
        $activeIndex = collect($this->chapters)
            ->search(fn($c) => $c['id'] === $this->activeChapterId);

        $doneCount      = collect($this->chapters)->where('status', 'done')->count();
        $pendingCount   = collect($this->chapters)->whereIn('status', ['pending', 'error'])->count();
        $totalCount     = count($this->chapters);

        return view('livewire.admin.audio-book-generator', compact(
            'activeIndex', 'doneCount', 'pendingCount', 'totalCount'
        ))->layout('layouts.admin');
    }
}
