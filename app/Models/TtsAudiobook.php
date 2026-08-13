<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\AudioSecurityService;
use Illuminate\Support\Facades\Storage;

class TtsAudiobook extends Model
{
    protected $table = 'tts_audiobooks';

    protected $fillable = [
        'mongo_id', 'book_title', 'variant_key', 'variant_name', 'book_author', 'language', 'speaker', 'engine',
        'public_speaker_name',
        'speaker_style', 'speaker_personality', 'expression_style',
        'prosody_rate', 'prosody_pitch', 'prosody_volume',
        'preview_chapter_number', 'preview_audio_path', 'preview_audio_url',
        'has_background_music', 'background_music_track', 'background_music_volume', 'tts_audio_volume',
    ];

    protected $casts = [
        'preview_chapter_number' => 'integer',
        'has_background_music' => 'boolean',
        'background_music_volume' => 'float',
        'tts_audio_volume' => 'float',
    ];

    public static function variantKeyFromAttributes(array $attributes): string
    {
        $parts = [
            static::normaliseVariantValue($attributes['engine'] ?? 'azure'),
            static::normaliseVariantValue($attributes['language'] ?? 'en-US'),
            static::normaliseVariantValue($attributes['speaker'] ?? 'en-US-AriaNeural'),
            static::normaliseVariantValue($attributes['speaker_style'] ?? $attributes['speakerStyle'] ?? ''),
            static::normaliseVariantValue($attributes['speaker_personality'] ?? $attributes['speakerPersonality'] ?? ''),
            static::normaliseVariantValue($attributes['expression_style'] ?? $attributes['expressionStyle'] ?? ''),
        ];

        return sha1(implode('|', $parts));
    }

    public static function variantLookup(array $attributes): array
    {
        return [
            'book_title' => trim((string) ($attributes['book_title'] ?? $attributes['bookTitle'] ?? '')),
            'variant_key' => static::variantKeyFromAttributes($attributes),
        ];
    }

    public static function variantSummaryFromAttributes(array $attributes): string
    {
        $parts = [
            trim((string) ($attributes['language'] ?? '')),
            trim((string) ($attributes['public_speaker_name'] ?? $attributes['speaker'] ?? '')),
        ];

        $style = trim((string) ($attributes['speaker_style'] ?? $attributes['speakerStyle'] ?? ''));
        if ($style !== '') {
            $parts[] = $style;
        }

        $personality = trim((string) ($attributes['speaker_personality'] ?? $attributes['speakerPersonality'] ?? ''));
        if ($personality !== '') {
            $parts[] = $personality;
        }

        $expressionStyle = trim((string) ($attributes['expression_style'] ?? $attributes['expressionStyle'] ?? ''));
        if ($expressionStyle !== '') {
            $parts[] = $expressionStyle;
        }

        $engine = trim((string) ($attributes['engine'] ?? ''));
        if ($engine !== '') {
            $parts[] = strtoupper($engine);
        }

        return implode(' | ', array_values(array_filter($parts)));
    }

    public function variantSummary(): string
    {
        return static::variantSummaryFromAttributes($this->toArray());
    }

    public function speakerDisplayName(): string
    {
        $publicSpeakerName = trim((string) ($this->public_speaker_name ?? ''));

        return $publicSpeakerName !== ''
            ? $publicSpeakerName
            : trim((string) ($this->speaker ?? ''));
    }

    public function variantAdminLabel(): string
    {
        $parts = [];

        $variantName = trim((string) ($this->variant_name ?? ''));
        if ($variantName !== '') {
            $parts[] = $variantName;
        }

        $parts[] = trim((string) ($this->language ?? ''));
        $parts[] = $this->speakerDisplayName();

        $speakerStyle = trim((string) ($this->speaker_style ?? ''));
        if ($speakerStyle !== '') {
            $parts[] = $speakerStyle;
        }

        $speakerPersonality = trim((string) ($this->speaker_personality ?? ''));
        if ($speakerPersonality !== '') {
            $parts[] = $speakerPersonality;
        }

        $expressionStyle = trim((string) ($this->expression_style ?? ''));
        if ($expressionStyle !== '') {
            $parts[] = $expressionStyle;
        }

        return implode(' | ', array_values(array_filter($parts)));
    }

    public function adminSelectionLabel(): string
    {
        $variant = $this->variantAdminLabel();

        return $variant !== ''
            ? trim((string) $this->book_title) . ' | ' . $variant
            : trim((string) $this->book_title);
    }

    public function variantLabel(): string
    {
        $variantName = trim((string) ($this->variant_name ?? ''));

        return $variantName !== '' ? $variantName : $this->variantSummary();
    }

    private static function normaliseVariantValue(mixed $value): string
    {
        return strtolower(trim((string) ($value ?? '')));
    }

    public function chapters()
    {
        return $this->hasMany(TtsAudiobookChapter::class, 'audiobook_id')->orderBy('chapter_number');
    }

    public function resolvePreviewUrl(): string
    {
        if ($this->preview_audio_path && Storage::disk('local')->exists($this->preview_audio_path)) {
            return app(AudioSecurityService::class)
                ->generateSignedUrl($this->preview_audio_path, null, 60 * 24 * 30);
        }

        if ($this->preview_audio_url) {
            return (string) $this->preview_audio_url;
        }

        $chapter = $this->resolvePreviewChapter();
        if (!$chapter) {
            return '';
        }

        if ($chapter->audio_path && Storage::disk('local')->exists($chapter->audio_path)) {
            return app(AudioSecurityService::class)
                ->generateSignedUrl($chapter->audio_path, null, 60 * 24 * 30);
        }

        return (string) ($chapter->audio_url ?? '');
    }

    public function resolvePreviewTitle(): string
    {
        if ($this->preview_audio_path || $this->preview_audio_url) {
            return trim((string) $this->book_title) . ' Preview';
        }

        $chapter = $this->resolvePreviewChapter();
        if ($chapter) {
            return trim((string) $this->book_title) . ' - ' . trim((string) $chapter->title);
        }

        return trim((string) $this->book_title);
    }

    public function resolvePreviewChapter(): ?TtsAudiobookChapter
    {
        $chapters = $this->relationLoaded('chapters')
            ? $this->chapters
            : $this->chapters()->get();

        if ($this->preview_chapter_number) {
            $selected = $chapters->firstWhere('chapter_number', $this->preview_chapter_number);
            if ($selected && $this->chapterHasPlayableAudio($selected)) {
                return $selected;
            }

            return null;
        }

        return $chapters->first(fn (TtsAudiobookChapter $chapter) => $this->chapterHasPlayableAudio($chapter));
    }

    private function chapterHasPlayableAudio(TtsAudiobookChapter $chapter): bool
    {
        return ($chapter->status === 'done' || $chapter->status === null)
            && ($chapter->audio_path || $chapter->audio_url);
    }
}
