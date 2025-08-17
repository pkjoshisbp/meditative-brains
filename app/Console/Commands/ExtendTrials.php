<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;

class ExtendTrials extends Command
{
    protected $signature = 'trials:extend {--days=7 : Number of days to add} {--plan= : Restrict to a plan slug} {--limit=0 : Limit number of records}';
    protected $description = 'Extend ends_at for active trial subscriptions';

    public function handle(): int
    {
        $days = (int)$this->option('days');
        $plan = $this->option('plan');
        $limit = (int)$this->option('limit');

        $query = Subscription::where('is_trial',true)->active();
        if ($plan) { $query->where('plan_type',$plan); }
        $subs = $query->get();
        $count = 0;
        foreach ($subs as $sub) {
            if ($limit && $count >= $limit) break;
            $sub->ends_at = $sub->ends_at->addDays($days);
            $sub->save();
            $count++;
        }
        $this->info("Trials extended: $count ( +$days days each )");
        return Command::SUCCESS;
    }
}
