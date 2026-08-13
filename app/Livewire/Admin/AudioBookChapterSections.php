<?php

namespace App\Livewire\Admin;

use App\Models\TtsAudiobook;
use App\Models\TtsAudiobookChapter;
use App\Services\AudiobookChapterChunkService;
use App\Services\AudioSecurityService;
use App\WebSocket\TtsWebSocketServer;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class AudioBookChapterSections extends Component
{
    public int $bookId;
    public int $chapterNumber;
    public string $bookTitle = '';
    public string $chapterTitle = '';
    public bool $usesSsml = false;
    public array $sections = [];
    public array $dirtyChunkNumbers = [];
    public string $statusMessage = '';
    public ?string $chapterAudioUrl = null;

    public function mount(int $bookId, int $chapterNumber): void
    {
        $this->bookId = $bookId;
        $this->chapterNumber = $chapterNumber;

        $this->reloadState();
    }

    public function saveSections(): void
    {
        try {
            [, $chapter, $sync] = $this->persistSections();

            $this->statusMessage = $sync['chapterDirty'] || empty($chapter->audio_path)
                ? 'success:Sections saved. Regenerate changed sections to rebuild the chapter audio.'
                : 'success:Sections saved. No audio regeneration is needed.';
        } catch (\Throwable $e) {
            $this->statusMessage = 'error:' . $e->getMessage();
        }
    }

    public function regenerateChangedSections(): void
    {
        try {
            [$book, $chapter, $sync] = $this->persistSections();
            $result = app(AudiobookChapterChunkService::class)->generateChapterAudio(
                $book,
                $chapter,
                $this->voiceOptions($book),
                $sync['dirtyChunkNumbers']
            );

            $generatedCount = count($result['generatedChunkNumbers']);
            $book->touch();
            TtsWebSocketServer::touchCatalogVersion($book->language, 'audiobook.chapter.updated');

            $this->reloadState();
            $this->statusMessage = 'success:'
                . ($generatedCount > 0
                    ? "Regenerated {$generatedCount} changed section(s) and rebuilt the chapter audio."
                    : 'Rebuilt the chapter audio without any new Azure section generation.');
        } catch (\Throwable $e) {
            $this->statusMessage = 'error:' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin.audio-book-chapter-sections')
            ->layout('components.layouts.admin', [
                'title' => 'Audiobook Chapter Sections',
            ]);
    }

    private function persistSections(): array
    {
        $book = TtsAudiobook::find($this->bookId);
        if (!$book) {
            throw new \RuntimeException('Audiobook not found.');
        }

        $chapter = $book->chapters()->where('chapter_number', $this->chapterNumber)->first();
        if (!$chapter) {
            throw new \RuntimeException('Chapter not found.');
        }

        $content = app(AudiobookChapterChunkService::class)->composeChapterContent($this->sections);
        if ($content === '') {
            throw new \RuntimeException('At least one non-empty section is required.');
        }

        $chapter->title = trim($this->chapterTitle) !== '' ? trim($this->chapterTitle) : $chapter->title;
        if ($this->usesSsml) {
            $chapter->ssml_content = $content;
        } else {
            $chapter->plain_content = $content;
        }
        $chapter->save();

        $sync = app(AudiobookChapterChunkService::class)->synchroniseChapter($chapter);
        $this->hydrateState($book, $chapter->fresh());

        return [$book, $chapter->fresh(), $sync];
    }

    private function reloadState(): void
    {
        $book = TtsAudiobook::find($this->bookId);
        if (!$book) {
            abort(404);
        }

        $chapter = $book->chapters()->where('chapter_number', $this->chapterNumber)->first();
        if (!$chapter) {
            abort(404);
        }

        app(AudiobookChapterChunkService::class)->synchroniseChapter($chapter);
        $this->hydrateState($book, $chapter->fresh());
    }

    private function hydrateState(TtsAudiobook $book, TtsAudiobookChapter $chapter): void
    {
        $manifest = $chapter->chunk_manifest ?? [];

        $this->bookTitle = (string) $book->book_title;
        $this->chapterTitle = (string) $chapter->title;
        $this->usesSsml = app(AudiobookChapterChunkService::class)->usesSsml($chapter);
        $this->sections = array_map(fn (array $entry) => [
            'text' => (string) ($entry['text'] ?? ''),
            'status' => (string) ($entry['status'] ?? 'pending'),
        ], $manifest);
        $this->dirtyChunkNumbers = array_values(array_map(
            fn (array $entry) => (int) $entry['sequence'],
            array_filter($manifest, fn (array $entry) => ($entry['status'] ?? 'pending') !== 'done')
        ));
        $this->chapterAudioUrl = $this->resolveChapterAudioUrl($chapter);
    }

    private function resolveChapterAudioUrl(TtsAudiobookChapter $chapter): ?string
    {
        if ($chapter->audio_path && Storage::disk('local')->exists($chapter->audio_path)) {
            return app(AudioSecurityService::class)->generateSignedUrl($chapter->audio_path, null, 60 * 24 * 30);
        }

        return $chapter->audio_url ?: null;
    }

    private function voiceOptions(TtsAudiobook $book): array
    {
        return [
            'language' => $book->language,
            'speaker' => $book->speaker,
            'engine' => $book->engine,
            'speakerStyle' => $book->speaker_style,
            'speakerPersonality' => $book->speaker_personality,
            'expressionStyle' => $book->expression_style,
            'prosodyRate' => $book->prosody_rate,
            'prosodyPitch' => $book->prosody_pitch,
            'prosodyVolume' => $book->prosody_volume,
        ];
    }
}
