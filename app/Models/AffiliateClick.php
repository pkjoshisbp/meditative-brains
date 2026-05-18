<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_profile_id',
        'visitor_user_id',
        'session_id',
        'landing_url',
        'referrer_url',
        'ip_address',
        'user_agent',
        'converted_at',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function affiliateProfile()
    {
        return $this->belongsTo(AffiliateProfile::class);
    }

    public function visitorUser()
    {
        return $this->belongsTo(User::class, 'visitor_user_id');
    }
}