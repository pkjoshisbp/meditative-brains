<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\Mail;
use App\Mail\TrialEndingSoon;

class NotifyTrialsEnding extends Command
{
    protected $signature = 'trials:notify-ending {--days=2 : Notify trials ending within this many days} {--plan= : Restrict to plan slug} {--dry-run : List only, do not email}';
    protected $description = 'Send notifications to users whose trial ends soon';

    public function handle(): int
    {
        $days = (int)$this->option('days');
        $plan = $this->option('plan');
        $dry = $this->option('dry-run');
        $thresholdStart = now();
        $thresholdEnd = now()->addDays($days);
        $query = Subscription::where('is_trial',true)
            ->where('status','active')
            ->whereBetween('ends_at', [$thresholdStart, $thresholdEnd]);
        if ($plan) { $query->where('plan_type',$plan); }
        $subs = $query->get();
        $sent=0; $listed=0;
        foreach ($subs as $sub) {
            $listed++;
            if (!$dry && $sub->user && $sub->user->email) {
                Mail::to($sub->user->email)->queue(new TrialEndingSoon($sub));
                $sent++;
            }
        }
        $this->info(($dry?'[Dry Run] ':'')."Trials listed: $listed | Emails queued: $sent | Window: now..+$days d");
        return Command::SUCCESS;
    }
}
