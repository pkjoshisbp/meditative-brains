<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TtsSourceCategory extends Model
{
    protected $fillable = ['mongo_id', 'category', 'user_id'];

    public function messages()
    {
        return $this->hasMany(TtsMotivationMessage::class, 'source_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function audioProduct()
    {
        return $this->hasOne(TtsAudioProduct::class, 'backend_category_id', 'mongo_id');
    }
}
