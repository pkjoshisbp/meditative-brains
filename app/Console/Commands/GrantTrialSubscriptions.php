<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{User, SubscriptionPlan, Subscription};
use App\Models\TrialEvent;

class GrantTrialSubscriptions extends Command
{
    protected $signature = 'users:grant-trial {--plan=premium-all-access : Plan slug to grant trials for} {--role=trial : Role to assign} {--force : Grant even if user already has active subscription} {--days= : Override trial days} {--limit=0 : Limit number of users processed (0=all)}';
    protected $description = 'Grant trial subscriptions to existing users, assigning role and creating subscription records';

    public function handle(): int
    {
        $planSlug = $this->option('plan');
        $role = $this->option('role');
        $force = $this->option('force');
        $overrideDays = $this->option('days') ? (int)$this->option('days') : null;
        $limit = (int)$this->option('limit');

        $plan = SubscriptionPlan::where('slug',$planSlug)->first();
        if (!$plan) { $this->error("Plan not found: $planSlug"); return Command::FAILURE; }
        $trialDays = $overrideDays ?? ($plan->trial_days ?? 7);

        $query = User::query();
        $total = 0; $granted = 0; $skipped = 0;
        $query->orderBy('id');
        $users = $query->get();
        foreach ($users as $user) {
            if ($limit && $granted >= $limit) break;
            $total++;
            $hasActive = $user->subscriptions()->active()->exists();
            if ($hasActive && !$force) { $skipped++; continue; }
            $starts = now();
            $ends = $starts->copy()->addDays($trialDays);
            Subscription::create([
                'user_id' => $user->id,
                'plan_type' => $plan->slug,
                'price' => 0.00,
                'status' => 'active',
                'starts_at' => $starts,
                'ends_at' => $ends,
                'auto_renew' => false,
                'is_trial' => true,
            ]);
            TrialEvent::create([
                'user_id' => $user->id,
                'event_type' => 'started',
                'plan_type' => $plan->slug,
                'meta' => ['ends_at' => $ends],
            ]);
            if ($role) {
                $user->role = $role;
                $user->save();
            }
            $granted++;
        }
        $this->info("Processed: $total | Granted: $granted | Skipped(active/no-force): $skipped | Plan: $planSlug | Trial days: $trialDays");
        return Command::SUCCESS;
    }
}
