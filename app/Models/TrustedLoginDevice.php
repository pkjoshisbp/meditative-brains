<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrustedLoginDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'selector',
        'token_hash',
        'device_name',
        'user_agent',
        'ip_address',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}