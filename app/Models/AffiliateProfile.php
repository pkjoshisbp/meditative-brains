<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'referral_code',
        'status',
        'commission_rate',
        'payout_email',
        'application_notes',
        'admin_notes',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function clicks()
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function conversions()
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    public function payouts()
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}