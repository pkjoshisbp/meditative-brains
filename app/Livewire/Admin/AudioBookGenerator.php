<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\TtsAudiobook;
use App\Models\TtsAudiobookChapter;
use App\Services\TtsAudioGeneratorService;
use App\Services\AudioSecurityService;

class AudioBookGenerator extends Component
{
    use WithFileUploads;

    // Book metadata
    public string $bookTitle  = 'Practicing Happiness';
    public string $bookAuthor = 'Pawan Joshi';
    public string $variantName = '';
    public string $publicSpeakerName = '';

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
    public string $chapterSaveStatus   = '';   // "success:ID" or "error:msg"
    public ?int   $savedBookId         = null; // MySQL id of the saved book
    public ?string $loadedVariantSignature = null;
    public        $recordedAudio       = null; // Temporary uploaded voice recording
    public        $previewAudioUpload  = null;

    // ── Voice data ───────────────────────────────────────────────────
    public array $languages               = [];
    public array $speakers                = [];
    public array $availableStyles         = [];
    public string $expressionStyle        = '';
    public array $availableExpressionStyles = [];
    public array $availablePersonalities  = [];
    public array $savedBooks              = []; // list for load dropdown
    public string $previewChapterNumber   = '';
    public string $customPreviewAudioPath = '';
    public string $customPreviewAudioUrl  = '';
    public string $resolvedPreviewUrl     = '';
    public string $resolvedPreviewLabel   = '';
    public string $previewStatus          = '';

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
        $this->syncResolvedPreview();
    }

    private function loadSavedBooksList(): void
    {
        $this->savedBooks = TtsAudiobook::select(
                'id',
                'book_title',
            'variant_name',
                'book_author',
                'language',
                'speaker',
                'public_speaker_name',
                'engine',
                'speaker_style',
                'speaker_personality',
                'expression_style',
                'preview_chapter_number',
                'preview_audio_path',
                'preview_audio_url'
            )
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('book_title')
            ->map(function ($books, $title) {
                return [
                    'book_title' => $title,
                    'variants' => $books->map(fn (TtsAudiobook $book) => [
                        'id' => $book->id,
                        'book_title' => $book->book_title,
                        'book_author' => $book->book_author,
                        'label' => $book->variantAdminLabel(),
                        'summary' => $book->variantSummary(),
                    ])->values()->toArray(),
                ];
            })
            ->values()
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

    public function updatedPreviewChapterNumber(): void
    {
        $this->syncResolvedPreview();
    }

    // ─────────────────────────────────────────────────────────────────
    // Chapter management
    // ─────────────────────────────────────────────────────────────────

    public function setActiveChapter(int $id): void
    {
        $this->activeChapterId  = $id;
        $this->activeTab        = 'ssml';
        $this->chapterSaveStatus = '';
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
        $this->syncResolvedPreview();
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
        $this->syncResolvedPreview();
    }

    public function moveUp(int $id): void
    {
        $i = collect($this->chapters)->search(fn($c) => $c['id'] === $id);
        if ($i > 0) {
            [$this->chapters[$i - 1], $this->chapters[$i]] =
                [$this->chapters[$i], $this->chapters[$i - 1]];
            $this->syncResolvedPreview();
        }
    }

    public function moveDown(int $id): void
    {
        $i = collect($this->chapters)->search(fn($c) => $c['id'] === $id);
        if ($i !== false && $i < count($this->chapters) - 1) {
            [$this->chapters[$i + 1], $this->chapters[$i]] =
                [$this->chapters[$i], $this->chapters[$i + 1]];
            $this->syncResolvedPreview();
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

                    $this->syncResolvedPreview();
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
                $this->syncResolvedPreview();
        }
    }

    /**
     * Save just the chapter's content (title, plain_content, ssml_content) to MySQL
     * WITHOUT generating audio. Audio path/url/status are left untouched.
     */
    public function saveChapterContent(int $id): void
    {
        $index = collect($this->chapters)->search(fn($c) => $c['id'] === $id);
        if ($index === false) return;

        if (empty(trim($this->bookTitle))) {
            $this->chapterSaveStatus = 'error:Book title is required before saving.';
            return;
        }

        try {
            $variantLookup = TtsAudiobook::variantLookup([
                'book_title' => $this->bookTitle,
                'engine' => $this->engine,
                'language' => $this->language,
                'speaker' => $this->speaker,
                'speaker_style' => $this->speakerStyle,
                'speaker_personality' => $this->speakerPersonality,
                'expression_style' => $this->expressionStyle,
            ]);

            // Ensure the parent book record exists in MySQL
            $book = TtsAudiobook::updateOrCreate(
                $variantLookup,
                [
                    'variant_key'         => $variantLookup['variant_key'],
                    'variant_name'        => trim($this->variantName) ?: null,
                    'book_author'         => $this->bookAuthor,
                    'language'            => $this->language,
                    'speaker'             => $this->speaker,
                    'public_speaker_name' => trim($this->publicSpeakerName) ?: null,
                    'engine'              => $this->engine,
                    'speaker_style'       => $this->speakerStyle ?: null,
                    'speaker_personality' => $this->speakerPersonality ?: null,
                    'expression_style'    => $this->expressionStyle ?: null,
                    'prosody_rate'        => $this->prosodyRate,
                    'prosody_pitch'       => $this->prosodyPitch,
                    'prosody_volume'      => $this->prosodyVolume,
                    'preview_chapter_number' => $this->previewChapterNumber !== '' ? (int) $this->previewChapterNumber : null,
                    'preview_audio_path'  => $this->customPreviewAudioPath ?: null,
                    'preview_audio_url'   => $this->customPreviewAudioUrl ?: null,
                ]
            );
            $this->savedBookId = $book->id;
            $this->loadedVariantSignature = $this->currentVoiceSignature();

            $ch = $this->chapters[$index];

            // Update ONLY content fields — audio_path, audio_url, and status are NOT touched
            TtsAudiobookChapter::updateOrCreate(
                ['audiobook_id' => $book->id, 'chapter_number' => $index + 1],
                [
                    'title'         => $ch['title'],
                    'plain_content' => $ch['plain_content'],
                    'ssml_content'  => $ch['ssml_content'],
                ]
            );

            $this->chapterSaveStatus = 'success:' . $id;
            $this->loadSavedBooksList();
            $this->syncResolvedPreview();
        } catch (\Exception $e) {
            $this->chapterSaveStatus = 'error:' . $e->getMessage();
            \Log::error('Chapter content save error', ['chapter_id' => $id, 'error' => $e->getMessage()]);
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

    /**
     * Accept a browser voice recording (webm/ogg), convert to AAC via FFmpeg,
     * encrypt it, and store it as the chapter's audio — without any TTS call.
     */
    public function saveRecordedAudio(int $id): void
    {
        $this->validate([
            'recordedAudio' => 'required|file|max:102400', // 100 MB max
        ]);

        $index = collect($this->chapters)->search(fn($c) => $c['id'] === $id);
        if ($index === false || !$this->recordedAudio) return;

        try {
            $this->_persistBook();

            $security = app(AudioSecurityService::class);
            $tts      = app(TtsAudioGeneratorService::class);
            $bookSlug = Str::slug($this->bookTitle);

            $ch   = $this->chapters[$index];
            $slug = Str::slug($ch['title'] ?: 'chapter-' . ($index + 1));
            $hash = substr(md5($ch['title'] . ($index + 1)), 0, 8);

            $relative = $this->language . '/' . $bookSlug . '/voice-recording/' . $slug . '-' . $hash . '-voice.aac';
            $absolute = storage_path('app/audiobook/' . $relative);

            if (!is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0775, true);
            }

            // Convert the uploaded audio (webm/ogg) to AAC via FFmpeg
            $tts->convertAudioToAac($this->recordedAudio->getRealPath(), $absolute);

            // Encrypt and sign
            $storageKey  = 'audiobook/' . $relative;
            $signedUrl   = $security->encryptRawAudioAndSign($absolute, $storageKey);
            $encRelative = 'audio/encrypted/tts-messages/' . preg_replace('/\.[^.]+$/', '', $storageKey) . '.enc';

            // Update Livewire state
            $this->chapters[$index]['status']    = 'done';
            $this->chapters[$index]['audio_url'] = $signedUrl;
            $this->chapters[$index]['error']     = null;

            // Persist to MySQL
            if ($this->savedBookId) {
                TtsAudiobookChapter::where('audiobook_id', $this->savedBookId)
                    ->where('chapter_number', $index + 1)
                    ->update([
                        'audio_path' => $encRelative,
                        'audio_url'  => $signedUrl,
                        'status'     => 'done',
                    ]);
            }

            $this->recordedAudio     = null;
            $this->chapterSaveStatus = 'success:' . $id;
            $this->syncResolvedPreview();

        } catch (\Exception $e) {
            $this->chapters[$index]['error'] = $e->getMessage();
            \Log::error('Voice recording save error', ['chapter_id' => $id, 'error' => $e->getMessage()]);
        }
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

    public function savePreviewSelection(): void
    {
        if (empty(trim($this->bookTitle))) {
            $this->previewStatus = 'error:Book title is required before saving preview settings.';
            return;
        }

        try {
            $this->_persistBook();
            $this->loadSavedBooksList();
            $this->syncResolvedPreview();
            $this->previewStatus = 'success:Preview settings saved.';
        } catch (\Exception $e) {
            $this->previewStatus = 'error:' . $e->getMessage();
        }
    }

    public function saveCustomPreviewAudio(): void
    {
        $this->validate([
            'previewAudioUpload' => 'required|file|max:102400',
        ]);

        if (empty(trim($this->bookTitle))) {
            $this->previewStatus = 'error:Book title is required before uploading a custom preview.';
            return;
        }

        try {
            $this->_persistBook();

            $security = app(AudioSecurityService::class);
            $tts = app(TtsAudioGeneratorService::class);
            $bookSlug = Str::slug($this->bookTitle);
            $relative = $this->language . '/' . $bookSlug . '/custom-preview-' . substr(md5($this->bookTitle . microtime(true)), 0, 10) . '.aac';
            $absolute = storage_path('app/audiobook-preview/' . $relative);

            if (!is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0775, true);
            }

            $tts->convertAudioToAac($this->previewAudioUpload->getRealPath(), $absolute);

            $storageKey = 'audiobook-preview/' . $relative;
            $signedUrl = $security->encryptRawAudioAndSign($absolute, $storageKey);
            $encRelative = 'audio/encrypted/tts-messages/' . preg_replace('/\.[^.]+$/', '', $storageKey) . '.enc';

            if ($this->customPreviewAudioPath) {
                Storage::disk('local')->delete($this->customPreviewAudioPath);
            }

            $this->customPreviewAudioPath = $encRelative;
            $this->customPreviewAudioUrl = $signedUrl;
            $this->previewAudioUpload = null;

            $this->_persistBook();
            $this->loadSavedBooksList();
            $this->syncResolvedPreview();
            $this->previewStatus = 'success:Custom preview audio saved.';
        } catch (\Exception $e) {
            $this->previewStatus = 'error:' . $e->getMessage();
        }
    }

    public function clearCustomPreviewAudio(): void
    {
        if ($this->customPreviewAudioPath) {
            Storage::disk('local')->delete($this->customPreviewAudioPath);
        }

        $this->customPreviewAudioPath = '';
        $this->customPreviewAudioUrl = '';
        $this->savePreviewSelection();
    }

    /** Upsert the book + chapters into MySQL. Sets $this->savedBookId. */
    private function _persistBook(): void
    {
        $variantLookup = TtsAudiobook::variantLookup([
            'book_title' => $this->bookTitle,
            'engine' => $this->engine,
            'language' => $this->language,
            'speaker' => $this->speaker,
            'speaker_style' => $this->speakerStyle,
            'speaker_personality' => $this->speakerPersonality,
            'expression_style' => $this->expressionStyle,
        ]);

        $book = TtsAudiobook::updateOrCreate(
            $variantLookup,
            [
                'variant_key'         => $variantLookup['variant_key'],
                'variant_name'        => trim($this->variantName) ?: null,
                'book_author'         => $this->bookAuthor,
                'language'            => $this->language,
                'speaker'             => $this->speaker,
                'public_speaker_name' => trim($this->publicSpeakerName) ?: null,
                'engine'              => $this->engine,
                'speaker_style'       => $this->speakerStyle ?: null,
                'speaker_personality' => $this->speakerPersonality ?: null,
                'expression_style'    => $this->expressionStyle ?: null,
                'prosody_rate'        => $this->prosodyRate,
                'prosody_pitch'       => $this->prosodyPitch,
                'prosody_volume'      => $this->prosodyVolume,
                'preview_chapter_number' => $this->previewChapterNumber !== '' ? (int) $this->previewChapterNumber : null,
                'preview_audio_path'  => $this->customPreviewAudioPath ?: null,
                'preview_audio_url'   => $this->customPreviewAudioUrl ?: null,
            ]
        );
        $this->savedBookId = $book->id;
        $this->loadedVariantSignature = $this->currentVoiceSignature();

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
            $this->loadedVariantSignature = $this->voiceSignatureFor($book->engine, $book->language, $book->speaker);
            $this->bookTitle       = $book->book_title;
            $this->bookAuthor      = $book->book_author;
            $this->variantName     = $book->variant_name ?? '';
            $this->language        = $book->language;
            $this->speaker         = $book->speaker;
            $this->publicSpeakerName = $book->public_speaker_name ?? '';
            $this->engine          = $book->engine;
            $this->speakerStyle       = $book->speaker_style       ?? '';
            $this->speakerPersonality = $book->speaker_personality ?? '';
            $this->expressionStyle    = $book->expression_style    ?? '';
            $this->prosodyRate        = $book->prosody_rate;
            $this->prosodyPitch       = $book->prosody_pitch;
            $this->prosodyVolume      = $book->prosody_volume;
            $this->previewChapterNumber = $book->preview_chapter_number ? (string) $book->preview_chapter_number : '';
            $this->customPreviewAudioPath = $book->preview_audio_path ?? '';
            $this->customPreviewAudioUrl = $book->preview_audio_url ?? '';

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
            $this->syncResolvedPreview();
            $count = count($this->chapters);
            $loadedVariantLabel = $book->variantLabel();
            $this->importStatus = "success:Loaded \"{$this->bookTitle}\" ({$loadedVariantLabel}) with {$count} chapters.";
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

        $isCurrentAudioVariant = $this->isCurrentAudioVariantLoaded();
        $doneCount      = $isCurrentAudioVariant ? collect($this->chapters)->where('status', 'done')->count() : 0;
        $pendingCount   = $isCurrentAudioVariant
            ? collect($this->chapters)->whereIn('status', ['pending', 'error'])->count()
            : count($this->chapters);
        $totalCount     = count($this->chapters);

        return view('livewire.admin.audio-book-generator', compact(
            'activeIndex', 'doneCount', 'pendingCount', 'totalCount', 'isCurrentAudioVariant'
        ))->layout('components.layouts.admin', [
            'title' => 'Audiobook Generator',
        ]);
    }

    private function currentVoiceSignature(): string
    {
        return $this->voiceSignatureFor($this->engine, $this->language, $this->speaker);
    }

    private function voiceSignatureFor(?string $engine, ?string $language, ?string $speaker): string
    {
        return implode('|', [
            strtolower(trim((string) ($engine ?? ''))),
            strtolower(trim((string) ($language ?? ''))),
            strtolower(trim((string) ($speaker ?? ''))),
        ]);
    }

    private function isCurrentAudioVariantLoaded(): bool
    {
        return !empty($this->loadedVariantSignature)
            && $this->loadedVariantSignature === $this->currentVoiceSignature();
    }

    private function syncResolvedPreview(): void
    {
        $this->resolvedPreviewUrl = '';
        $this->resolvedPreviewLabel = '';

        if ($this->customPreviewAudioPath && Storage::disk('local')->exists($this->customPreviewAudioPath)) {
            $this->resolvedPreviewUrl = app(AudioSecurityService::class)
                ->generateSignedUrl($this->customPreviewAudioPath, null, 60 * 24 * 30);
            $this->resolvedPreviewLabel = 'Custom uploaded preview';
            return;
        }

        if ($this->customPreviewAudioUrl) {
            $this->resolvedPreviewUrl = $this->customPreviewAudioUrl;
            $this->resolvedPreviewLabel = 'Custom uploaded preview';
            return;
        }

        if ($this->previewChapterNumber !== '') {
            $chapterIndex = ((int) $this->previewChapterNumber) - 1;
            $chapter = $this->chapters[$chapterIndex] ?? null;
            if ($chapter && !empty($chapter['audio_url'])) {
                $this->resolvedPreviewUrl = $chapter['audio_url'];
                $this->resolvedPreviewLabel = 'Chapter preview: ' . ($chapter['title'] ?: 'Chapter ' . $this->previewChapterNumber);
                return;
            }

            $this->resolvedPreviewLabel = 'Selected preview chapter has no generated audio yet.';
            return;
        }

        foreach ($this->chapters as $chapter) {
            if (($chapter['status'] ?? '') === 'done' && !empty($chapter['audio_url'])) {
                $this->resolvedPreviewUrl = $chapter['audio_url'];
                $this->resolvedPreviewLabel = 'Fallback preview: ' . ($chapter['title'] ?: 'Generated chapter');
                return;
            }
        }
    }
}
