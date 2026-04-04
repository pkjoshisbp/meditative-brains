<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVersion extends Model
{
    protected $fillable = [
        'product_id',
        'version_label',
        'language',
        'accent',
        'product_type',
        'price',
        'inr_price',
        'audio_url',
        'pdf_file_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'inr_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(TtsAudioProduct::class, 'product_id');
    }
}
