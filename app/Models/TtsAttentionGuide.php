<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsAttentionGuide extends Model
{
    protected $table = 'tts_attention_guides';

    protected $fillable = [
        'mongo_id', 'text', 'language', 'speaker', 'engine',
        'speaker_style', 'category', 'speed', 'interval_ms', 'is_active',
        'audio_path', 'audio_url',
    ];

    protected $casts = [
        'interval_ms' => 'integer',
        'is_active'   => 'boolean',
    ];
}
