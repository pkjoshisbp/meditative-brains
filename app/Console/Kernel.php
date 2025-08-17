<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
    // $schedule->command('inspire')->hourly();
    $schedule->command('trials:expire')->dailyAt('00:10');
    $schedule->command('trials:notify-ending')->dailyAt('08:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
    protected $commands = [
        \App\Console\Commands\BackfillTtsGroupKeys::class,
        \App\Console\Commands\ImportNodeUsers::class,
        \App\Console\Commands\GrantTrialSubscriptions::class,
        \App\Console\Commands\RevokeTrialSubscriptions::class,
        \App\Console\Commands\ExtendTrials::class,
    \App\Console\Commands\ExpireTrials::class,
    \App\Console\Commands\NotifyTrialsEnding::class,
    ];
}
