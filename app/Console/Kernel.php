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
        // Process scheduled Instagram posts every minute
        $schedule->command('instagram:process-scheduled-posts')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();
                 
        // Proactive token refresh disabled - using long-lived tokens from auth
        // $schedule->job(new \App\Jobs\ProactiveTokenRefreshJob)
        //          ->daily()
        //          ->withoutOverlapping()
        //          ->runInBackground();
                 
        // Clean up failed jobs daily
        $schedule->command('queue:prune-failed --hours=48')
                 ->daily();
                 
        // Clean up stuck posts every 30 minutes
        $schedule->command('instagram:cleanup-stuck-posts --minutes=30')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();
                 
        // Retry failed posts every hour (for posts that failed in the last 2 hours)
        $schedule->command('instagram:retry-failed-posts --minutes=120')
                 ->hourly()
                 ->withoutOverlapping();
                 
        // Clear old log files weekly
        $schedule->command('log:clear')
                 ->weekly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
