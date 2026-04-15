<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsLanguage extends Model
{
    protected $table = 'tts_languages';

    protected $fillable = ['code', 'name', 'local_name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
