<?php

namespace App\Listeners;

use App\Events\TrialExpired;
use Illuminate\Support\Facades\Log;

class LogTrialExpired
{
    public function handle(TrialExpired $event): void
    {
        $sub = $event->subscription;
        Log::info('Trial expired', [
            'user_id' => $sub->user_id,
            'plan' => $sub->plan_type,
            'ended_at' => $sub->ends_at,
        ]);
    }
}
