<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsAudiobookChapter extends Model
{
    protected $table = 'tts_audiobook_chapters';

    protected $fillable = [
        'audiobook_id', 'chapter_number', 'title',
        'plain_content', 'ssml_content',
        'chunk_manifest',
        'audio_path', 'audio_url', 'status',
    ];

    protected $casts = [
        'chunk_manifest' => 'array',
    ];

    public function audiobook()
    {
        return $this->belongsTo(TtsAudiobook::class, 'audiobook_id');
    }
}
