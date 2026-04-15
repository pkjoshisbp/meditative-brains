<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsAudiobook extends Model
{
    protected $table = 'tts_audiobooks';

    protected $fillable = [
        'mongo_id', 'book_title', 'book_author', 'language', 'speaker', 'engine',
        'speaker_style', 'expression_style', 'prosody_rate', 'prosody_pitch', 'prosody_volume',
    ];

    public function chapters()
    {
        return $this->hasMany(TtsAudiobookChapter::class, 'audiobook_id')->orderBy('chapter_number');
    }
}
