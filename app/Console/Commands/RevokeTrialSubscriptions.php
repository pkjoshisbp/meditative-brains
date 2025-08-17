<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Subscription, User};

class RevokeTrialSubscriptions extends Command
{
    protected $signature = 'users:revoke-trial {--plan=premium-all-access : Plan slug to target} {--role=trial : Role value to clear} {--keep-role : Do not clear the role} {--dry-run : Show what would be removed}';
    protected $description = 'Revoke trial subscriptions and optionally clear trial role from users';

    public function handle(): int
    {
        $plan = $this->option('plan');
        $role = $this->option('role');
        $keepRole = $this->option('keep-role');
        $dry = $this->option('dry-run');

        $subs = Subscription::where('plan_type',$plan)->where('is_trial',true)->get();
        $count = 0; $roleCleared = 0;
        foreach ($subs as $sub) {
            $count++;
            if (!$dry) { $sub->delete(); }
            if (!$keepRole) {
                $u = $sub->user; if ($u && $u->role === $role) { if(!$dry){ $u->role = null; $u->save(); } $roleCleared++; }
            }
        }
        $this->info(($dry? '[Dry Run] ':'')."Trial subscriptions removed: $count | Roles cleared: $roleCleared");
        return Command::SUCCESS;
    }
}
