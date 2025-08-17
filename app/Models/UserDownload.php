<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','product_id','tts_audio_product_id','device_uuid','bytes','sha256','completed','completed_at'
    ];

    protected $casts = [
        'completed' => 'boolean',
        'bytes' => 'integer',
        'completed_at' => 'datetime'
    ];

    public function user(){ return $this->belongsTo(User::class); }
    public function product(){ return $this->belongsTo(Product::class); }
    public function ttsProduct(){ return $this->belongsTo(TtsAudioProduct::class,'tts_audio_product_id'); }
}
