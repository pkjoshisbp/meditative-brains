<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsMotivationMessage extends Model
{
    protected $fillable = [
        'mongo_id', 'source_category_id', 'user_id',
        'messages', 'ssml_messages', 'ssml',
        'engine', 'language', 'speaker',
        'speaker_style', 'speaker_personality',
        'prosody_pitch', 'prosody_rate', 'prosody_volume',
        'audio_paths', 'audio_urls', 'editable',
    ];

    protected $casts = [
        'messages'      => 'array',
        'ssml_messages' => 'array',
        'ssml'          => 'array',
        'audio_paths'   => 'array',
        'audio_urls'    => 'array',
        'editable'      => 'boolean',
    ];

    public function sourceCategory()
    {
        return $this->belongsTo(TtsSourceCategory::class, 'source_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
