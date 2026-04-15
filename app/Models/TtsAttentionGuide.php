<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsAttentionGuide extends Model
{
    protected $table = 'tts_attention_guides';

    protected $fillable = [
        'mongo_id', 'text', 'language', 'speaker', 'engine',
        'speaker_style', 'category', 'speed', 'audio_path', 'audio_url',
    ];
}
