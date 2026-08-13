<?php

namespace App\Services;

use App\Models\TtsAudiobook;
use App\Models\TtsAudiobookChapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AudiobookChapterChunkService
{
    public function __construct(
        private TtsAudioGeneratorService $tts,
        private AudioSecurityService $security,
    ) {
    }

    public function synchroniseChapter(TtsAudiobookChapter $chapter, bool $persist = true): array
    {
        $content = $this->resolveSourceContent($chapter);
        $segments = $this->splitIntoChunks($content);
        $existingManifest = $this->normaliseManifest($chapter->chunk_manifest ?? []);
        $hasLegacyChapterAudio = $existingManifest === [] && $this->chapterAudioExists($chapter);
        $existingQueues = [];

        foreach ($existingManifest as $entry) {
            $existingQueues[$entry['hash']][] = $entry;
        }

        $newManifest = [];
        $dirtyChunkNumbers = [];
        $existingHashes = array_map(fn (array $entry) => $entry['hash'], $existingManifest);
        $newHashes = [];

        foreach ($segments as $index => $text) {
            $hash = $this->chunkHash($text);
            $matched = null;

            if (!empty($existingQueues[$hash])) {
                $matched = array_shift($existingQueues[$hash]);
            }

            $audioPath = $matched['audio_path'] ?? null;
            if ($audioPath && !Storage::disk('local')->exists($audioPath)) {
                $audioPath = null;
            }

            $newManifest[] = [
                'sequence' => $index + 1,
                'text' => $text,
                'hash' => $hash,
                'audio_path' => $audioPath,
                'status' => $audioPath ? 'done' : 'pending',
            ];
            $newHashes[] = $hash;

            $samePosition = isset($existingManifest[$index])
                && $existingManifest[$index]['hash'] === $hash
                && $existingManifest[$index]['text'] === $text;

            if (!$samePosition || !$audioPath) {
                $dirtyChunkNumbers[] = $index + 1;
            }
        }

        $chapterDirty = $dirtyChunkNumbers !== [] || $existingHashes !== $newHashes;

        if ($persist) {
            $chapter->chunk_manifest = $newManifest;

            if ($chapterDirty && !$hasLegacyChapterAudio) {
                $chapter->audio_path = null;
                $chapter->audio_url = null;
                $chapter->status = 'pending';
            }

            $chapter->save();
        }

        return [
            'manifest' => $newManifest,
            'dirtyChunkNumbers' => array_values(array_unique($dirtyChunkNumbers)),
            'chapterDirty' => $chapterDirty,
        ];
    }

    public function generateChapterAudio(
        TtsAudiobook $book,
        TtsAudiobookChapter $chapter,
        array $voiceOptions,
        array $forceChunkNumbers = []
    ): array {
        $sync = $this->synchroniseChapter($chapter, false);
        $manifest = $sync['manifest'];
        $forceChunkNumbers = array_map('intval', $forceChunkNumbers);

        if ($manifest === []) {
            throw new \RuntimeException('No section content available for this chapter.');
        }

        if (
            $forceChunkNumbers === []
            && !$sync['chapterDirty']
            && $chapter->status === 'done'
            && $chapter->audio_path
            && Storage::disk('local')->exists($chapter->audio_path)
        ) {
            $signedUrl = $this->security->generateSignedUrl($chapter->audio_path, null, 60 * 24 * 365);
            $chapter->audio_url = $signedUrl;
            $chapter->save();

            return [
                'audio_url' => $signedUrl,
                'generatedChunkNumbers' => [],
                'manifest' => $chapter->chunk_manifest ?? $manifest,
            ];
        }

        $generatedChunkNumbers = [];
        $inputPaths = [];
        $chunkCategory = $this->chunkCategory($book, $chapter);

        foreach ($manifest as $index => $entry) {
            $chunkNumber = $index + 1;
            $needsGeneration = in_array($chunkNumber, $forceChunkNumbers, true)
                || empty($entry['audio_path'])
                || ($entry['status'] ?? 'pending') !== 'done';

            if ($needsGeneration) {
                $result = $this->tts->generateForMessage($entry['text'], array_merge($voiceOptions, [
                    'storageType' => 'audiobook',
                    'category' => $chunkCategory,
                ]));

                $entry['audio_path'] = $result['relativePath'];
                $entry['status'] = 'done';
                $generatedChunkNumbers[] = $chunkNumber;
            }

            $absolutePath = storage_path('app/' . ltrim((string) $entry['audio_path'], '/'));
            if (!file_exists($absolutePath)) {
                throw new \RuntimeException('Section audio file is missing: ' . $entry['audio_path']);
            }

            $inputPaths[] = $absolutePath;
            $manifest[$index] = $entry;
        }

        $compiledStoragePath = $this->compiledStoragePath($book, $chapter, $voiceOptions, $manifest);
        $compiledAbsolutePath = storage_path('app/' . $compiledStoragePath);
        $this->tts->concatenateAudioFiles($inputPaths, $compiledAbsolutePath);

        $signedUrl = $this->security->encryptRawAudioAndSign($compiledAbsolutePath, $compiledStoragePath, true);
        $encryptedPath = 'audio/encrypted/tts-messages/'
            . preg_replace('/\.[^.]+$/', '', ltrim($compiledStoragePath, '/'))
            . '.enc';

        $chapter->chunk_manifest = $manifest;
        $chapter->audio_path = $encryptedPath;
        $chapter->audio_url = $signedUrl;
        $chapter->status = 'done';
        $chapter->save();

        return [
            'audio_url' => $signedUrl,
            'generatedChunkNumbers' => $generatedChunkNumbers,
            'manifest' => $manifest,
        ];
    }

    public function composeChapterContent(array $segments): string
    {
        $segments = array_values(array_filter(array_map(
            fn ($segment) => $this->normaliseText((string) (is_array($segment) ? ($segment['text'] ?? '') : $segment)),
            $segments
        ), fn (string $segment) => $segment !== ''));

        return implode("\n\n", $segments);
    }

    public function usesSsml(TtsAudiobookChapter $chapter): bool
    {
        return trim((string) ($chapter->ssml_content ?? '')) !== '';
    }

    private function resolveSourceContent(TtsAudiobookChapter $chapter): string
    {
        $ssml = $this->normaliseText((string) ($chapter->ssml_content ?? ''));
        if ($ssml !== '') {
            return $ssml;
        }

        return $this->normaliseText((string) ($chapter->plain_content ?? ''));
    }

    private function splitIntoChunks(string $content): array
    {
        $content = $this->normaliseText($content);
        if ($content === '') {
            return [];
        }

        $paragraphs = preg_split('/\n\s*\n/', $content) ?: [];
        $paragraphs = array_values(array_filter(array_map([$this, 'normaliseText'], $paragraphs), fn ($text) => $text !== ''));

        if (count($paragraphs) > 1) {
            return $paragraphs;
        }

        if (str_contains($content, '<') || str_contains($content, '[')) {
            return [$content];
        }

        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"\'])/u', $content) ?: [];
        $sentences = array_values(array_filter(array_map([$this, 'normaliseText'], $sentences), fn ($text) => $text !== ''));

        return $sentences !== [] ? $sentences : [$content];
    }

    private function chunkCategory(TtsAudiobook $book, TtsAudiobookChapter $chapter): string
    {
        return implode('/', array_filter([
            Str::slug((string) $book->book_title),
            'chapter-' . str_pad((string) $chapter->chapter_number, 2, '0', STR_PAD_LEFT),
            'segments',
        ]));
    }

    private function compiledStoragePath(
        TtsAudiobook $book,
        TtsAudiobookChapter $chapter,
        array $voiceOptions,
        array $manifest
    ): string {
        $manifestFingerprint = substr(sha1(implode('|', array_map(fn (array $entry) => $entry['hash'], $manifest))), 0, 12);
        $settingsFingerprint = substr(sha1(implode('|', [
            $voiceOptions['engine'] ?? $book->engine ?? 'azure',
            $voiceOptions['language'] ?? $book->language ?? 'en-US',
            $voiceOptions['speaker'] ?? $book->speaker ?? 'voice',
            $voiceOptions['speakerStyle'] ?? '',
            $voiceOptions['speakerPersonality'] ?? '',
            $voiceOptions['expressionStyle'] ?? '',
            $voiceOptions['prosodyRate'] ?? '',
            $voiceOptions['prosodyPitch'] ?? '',
            $voiceOptions['prosodyVolume'] ?? '',
        ])), 0, 8);

        return 'audiobook/' . implode('/', array_filter([
            $book->language ?: 'en-US',
            Str::slug((string) $book->book_title) ?: 'audiobook',
            Str::slug((string) $book->speaker) ?: 'voice',
            'compiled',
            sprintf(
                'chapter-%02d-%s-%s-%s.aac',
                $chapter->chapter_number,
                Str::slug((string) $chapter->title) ?: 'chapter',
                $settingsFingerprint,
                $manifestFingerprint,
            ),
        ]));
    }

    private function normaliseManifest(array $manifest): array
    {
        $normalised = [];

        foreach ($manifest as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $text = $this->normaliseText((string) ($entry['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $audioPath = $entry['audio_path'] ?? null;
            $status = ($audioPath && Storage::disk('local')->exists($audioPath)) ? 'done' : 'pending';

            $normalised[] = [
                'sequence' => $index + 1,
                'text' => $text,
                'hash' => (string) ($entry['hash'] ?? $this->chunkHash($text)),
                'audio_path' => $audioPath,
                'status' => $status,
            ];
        }

        return $normalised;
    }

    private function chapterAudioExists(TtsAudiobookChapter $chapter): bool
    {
        $audioPath = trim((string) ($chapter->audio_path ?? ''));

        return $audioPath !== '' && Storage::disk('local')->exists($audioPath);
    }

    private function chunkHash(string $text): string
    {
        return sha1($this->normaliseText($text));
    }

    private function normaliseText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_map(fn ($line) => rtrim($line), explode("\n", $text));

        return trim(implode("\n", $lines));
    }
}
