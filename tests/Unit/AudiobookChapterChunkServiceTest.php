<?php

namespace Tests\Unit;

use App\Models\TtsAudiobookChapter;
use App\Services\AudiobookChapterChunkService;
use App\Services\AudioSecurityService;
use App\Services\TtsAudioGeneratorService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AudiobookChapterChunkServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_synchronise_marks_only_changed_section_dirty(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('audiobook/test/chunk-1.aac', 'first');
        Storage::disk('local')->put('audiobook/test/chunk-2.aac', 'second');

        $service = new AudiobookChapterChunkService(
            Mockery::mock(TtsAudioGeneratorService::class),
            Mockery::mock(AudioSecurityService::class),
        );

        $chapter = new TtsAudiobookChapter([
            'plain_content' => "First sentence.\n\nChanged second sentence.",
            'chunk_manifest' => [
                [
                    'sequence' => 1,
                    'text' => 'First sentence.',
                    'hash' => sha1('First sentence.'),
                    'audio_path' => 'audiobook/test/chunk-1.aac',
                    'status' => 'done',
                ],
                [
                    'sequence' => 2,
                    'text' => 'Second sentence.',
                    'hash' => sha1('Second sentence.'),
                    'audio_path' => 'audiobook/test/chunk-2.aac',
                    'status' => 'done',
                ],
            ],
        ]);

        $result = $service->synchroniseChapter($chapter, false);

        $this->assertSame([2], $result['dirtyChunkNumbers']);
        $this->assertSame('audiobook/test/chunk-1.aac', $result['manifest'][0]['audio_path']);
        $this->assertNull($result['manifest'][1]['audio_path']);
        $this->assertSame('done', $result['manifest'][0]['status']);
        $this->assertSame('pending', $result['manifest'][1]['status']);
    }

    public function test_synchronise_preserves_legacy_chapter_audio_when_creating_manifest(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('audio/encrypted/tts-messages/audiobook/test/chapter.enc', 'audio');

        $service = new AudiobookChapterChunkService(
            Mockery::mock(TtsAudioGeneratorService::class),
            Mockery::mock(AudioSecurityService::class),
        );

        $chapter = new class([
            'plain_content' => "First paragraph.\n\nSecond paragraph.",
            'chunk_manifest' => [],
            'audio_path' => 'audio/encrypted/tts-messages/audiobook/test/chapter.enc',
            'audio_url' => 'https://example.test/old-signed-url',
            'status' => 'done',
        ]) extends TtsAudiobookChapter {
            public function save(array $options = []): bool
            {
                return true;
            }
        };

        $result = $service->synchroniseChapter($chapter);

        $this->assertTrue($result['chapterDirty']);
        $this->assertSame('audio/encrypted/tts-messages/audiobook/test/chapter.enc', $chapter->audio_path);
        $this->assertSame('https://example.test/old-signed-url', $chapter->audio_url);
        $this->assertSame('done', $chapter->status);
    }
}
