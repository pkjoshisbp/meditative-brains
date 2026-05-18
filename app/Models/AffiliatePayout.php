<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliatePayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_profile_id',
        'amount',
        'currency',
        'status',
        'reference',
        'notes',
        'paid_at',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function affiliateProfile()
    {
        return $this->belongsTo(AffiliateProfile::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function conversions()
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_payout_id');
    }
}