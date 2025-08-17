<?php

namespace App\Listeners;

use App\Events\TrialExpired;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TrialExpiredMail;

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
        if ($sub->user && $sub->user->email) {
            Mail::to($sub->user->email)->queue(new TrialExpiredMail($sub));
        }
    }
}
