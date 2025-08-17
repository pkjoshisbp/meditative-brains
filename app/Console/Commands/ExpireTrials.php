<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Events\TrialExpired;
use App\Models\TrialEvent;

class ExpireTrials extends Command
{
    protected $signature = 'trials:expire {--plan= : Restrict to plan slug} {--dry-run : Show what would be expired} {--clear-role=trial : Clear role value for expired-only users}';
    protected $description = 'Mark ended trial subscriptions as expired and dispatch events';

    public function handle(): int
    {
        $plan = $this->option('plan');
        $dry = $this->option('dry-run');
        $clearRole = $this->option('clear-role');
        $query = Subscription::where('is_trial',true)
            ->where('status','active')
            ->where('ends_at','<=', now());
        if ($plan) { $query->where('plan_type',$plan); }
        $subs = $query->get();
        $count=0; $rolesCleared=0;
        foreach ($subs as $sub) {
            $count++;
            if (!$dry) {
                $sub->status='expired';
                $sub->save();
                event(new TrialExpired($sub));
                if ($clearRole && $sub->user && $sub->user->role === $clearRole) {
                    $sub->user->role = null; $sub->user->save(); $rolesCleared++;
                }
                TrialEvent::create([
                    'user_id' => $sub->user_id,
                    'event_type' => 'expired',
                    'plan_type' => $sub->plan_type,
                    'meta' => ['expired_at' => now()],
                ]);
            }
        }
        $this->info(($dry?'[Dry Run] ':'')."Trials expired: $count | Roles cleared: $rolesCleared");
        return Command::SUCCESS;
    }
}
