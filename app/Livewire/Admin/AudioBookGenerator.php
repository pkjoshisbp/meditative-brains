<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Models\TtsAudiobook;
use App\Models\TtsAudiobookChapter;
use App\Services\TtsAudioGeneratorService;
use App\Services\AudioSecurityService;

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
    public string $speakerPersonality = '';
    public string $prosodyRate  = 'medium';
    public string $prosodyPitch = 'medium';
    public string $prosodyVolume = 'medium';
    // Custom values for rate/pitch/volume when set to 'custom'
    public string $customRate  = '';
    public string $customPitch = '';
    public string $customVolume = '';

    // ── Runtime state ────────────────────────────────────────────────
    public ?int   $generatingChapterId = null;
    public string $importStatus        = '';   // "success:msg" or "error:msg"
    public ?int   $savedBookId         = null; // MySQL id of the saved book

    // ── Voice data ───────────────────────────────────────────────────
    public array $languages               = [];
    public array $speakers                = [];
    public array $availableStyles         = [];
    public string $expressionStyle        = '';
    public array $availableExpressionStyles = [];
    public array $availablePersonalities  = [];
    public array $savedBooks              = []; // list for load dropdown

    // ─────────────────────────────────────────────────────────────────
    // Lifecycle
    // ─────────────────────────────────────────────────────────────────

    public function mount(): void
    {
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
        $this->savedBooks = TtsAudiobook::select('id', 'book_title', 'book_author')
            ->orderByDesc('updated_at')
            ->get()
            ->toArray();
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
        // Personalities
        $personalities = $voice['VoiceTag']['VoicePersonalities'] ?? ($voice['RolePlayList'] ?? []);
        $this->availablePersonalities = $personalities;
        if (!in_array($this->speakerPersonality, $this->availablePersonalities)) {
            $this->speakerPersonality = '';
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
        $personalities = $voice['VoiceTag']['VoicePersonalities'] ?? ($voice['RolePlayList'] ?? []);
        $this->availablePersonalities = $personalities;
        if (!in_array($this->speakerPersonality, $this->availablePersonalities)) {
            $this->speakerPersonality = '';
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
            set_time_limit(300); // Allow up to 5 min for large chapters

            // Auto-save the book to MySQL so we have a real book ID
            $this->_persistBook();

            $tts      = app(TtsAudioGeneratorService::class);
            $security = app(AudioSecurityService::class);
            $bookSlug = Str::slug($this->bookTitle);

            $result = $tts->generateForMessage($content, [
                'language'           => $this->language,
                'speaker'            => $this->speaker,
                'engine'             => $this->engine,
                'speakerStyle'       => $this->speakerStyle ?: null,
                'speakerPersonality' => $this->speakerPersonality ?: null,
                'expressionStyle'    => $this->expressionStyle ?: null,
                'prosodyRate'        => $this->prosodyRate === 'custom' ? $this->customRate  : $this->prosodyRate,
                'prosodyPitch'       => $this->prosodyPitch === 'custom' ? $this->customPitch : $this->prosodyPitch,
                'prosodyVolume'      => $this->prosodyVolume === 'custom' ? $this->customVolume : $this->prosodyVolume,
                'storageType'        => 'audiobook',
                'category'           => $bookSlug,
            ]);

            // Encrypt and sign
            $signedUrl   = $security->encryptRawAudioAndSign(
                $result['absolutePath'],
                $result['relativePath']
            );
            $encRelative = 'audio/encrypted/tts-messages/' .
                preg_replace('/\.[^.]+$/', '', ltrim($result['relativePath'], '/')) . '.enc';

            // Update Livewire state
            $this->chapters[$index]['status']    = 'done';
            $this->chapters[$index]['audio_url'] = $signedUrl;
            $this->chapters[$index]['error']     = null;

            // Persist chapter audio to MySQL
            if ($this->savedBookId) {
                TtsAudiobookChapter::where('audiobook_id', $this->savedBookId)
                    ->where('chapter_number', $index + 1)
                    ->update([
                        'audio_path' => $encRelative,
                        'audio_url'  => $signedUrl,
                        'status'     => 'done',
                    ]);
            }
        } catch (\Exception $e) {
            $this->chapters[$index]['status'] = 'error';
            $this->chapters[$index]['error']  = $e->getMessage();
            \Log::error('AudioBook chapter generation error', ['chapter_id' => $id, 'message' => $e->getMessage()]);
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

    /**
     * Regenerate audio for ALL chapters, even those already done (overwrite).
     */
    public function generateAllForce(): void
    {
        foreach ($this->chapters as $chapter) {
            $this->generateChapter($chapter['id']);
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

        try {
            $this->_persistBook();
            $this->loadSavedBooksList();
            $this->importStatus = 'success:Book saved successfully!';
        } catch (\Exception $e) {
            $this->importStatus = 'error:Save error: ' . $e->getMessage();
        }
    }

    /** Upsert the book + chapters into MySQL. Sets $this->savedBookId. */
    private function _persistBook(): void
    {
        $book = TtsAudiobook::updateOrCreate(
            ['book_title' => $this->bookTitle],
            [
                'book_author'         => $this->bookAuthor,
                'language'            => $this->language,
                'speaker'             => $this->speaker,
                'engine'              => $this->engine,
                'speaker_style'       => $this->speakerStyle ?: null,
                'speaker_personality' => $this->speakerPersonality ?: null,
                'expression_style'    => $this->expressionStyle ?: null,
                'prosody_rate'        => $this->prosodyRate,
                'prosody_pitch'       => $this->prosodyPitch,
                'prosody_volume'      => $this->prosodyVolume,
            ]
        );
        $this->savedBookId = $book->id;

        foreach ($this->chapters as $i => $ch) {
            TtsAudiobookChapter::updateOrCreate(
                ['audiobook_id' => $book->id, 'chapter_number' => $i + 1],
                [
                    'title'         => $ch['title'],
                    'plain_content' => $ch['plain_content'],
                    'ssml_content'  => $ch['ssml_content'],
                    'audio_path'    => '',
                    'audio_url'     => $ch['audio_url'] ?? '',
                    'status'        => $ch['status'] === 'done' ? 'done' : 'pending',
                ]
            );
        }
    }

    public function loadBook(int $bookId): void
    {
        try {
            $book = TtsAudiobook::with(['chapters' => fn($q) => $q->orderBy('chapter_number')])->find($bookId);
            if (!$book) {
                $this->importStatus = 'error:Book not found.';
                return;
            }

            $this->savedBookId     = $book->id;
            $this->bookTitle       = $book->book_title;
            $this->bookAuthor      = $book->book_author;
            $this->language        = $book->language;
            $this->speaker         = $book->speaker;
            $this->engine          = $book->engine;
            $this->speakerStyle       = $book->speaker_style       ?? '';
            $this->speakerPersonality = $book->speaker_personality ?? '';
            $this->expressionStyle    = $book->expression_style    ?? '';
            $this->prosodyRate        = $book->prosody_rate;
            $this->prosodyPitch       = $book->prosody_pitch;
            $this->prosodyVolume      = $book->prosody_volume;

            $this->chapters = $book->chapters
                ->values()
                ->map(fn($ch, $i) => [
                    'id'            => $i + 1,
                    'title'         => $ch->title,
                    'plain_content' => $ch->plain_content ?? '',
                    'ssml_content'  => $ch->ssml_content ?? '',
                    'status'        => ($ch->status === 'done' && !empty($ch->audio_url)) ? 'done' : 'pending',
                    'audio_url'     => $ch->audio_url ?? null,
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
        ))->layout('components.layouts.admin', [
            'title' => 'Audiobook Generator',
        ]);
    }
}
