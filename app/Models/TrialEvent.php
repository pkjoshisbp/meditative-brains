<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id','event_type','plan_type','meta'];
    protected $casts = [
        'meta' => 'array',
    ];
}
