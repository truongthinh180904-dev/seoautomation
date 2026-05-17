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
        // Process keywords scheduled for AI pipeline — every 5 minutes
        $schedule->command('seo:process-scheduled')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // Detect and reset stalled jobs — every 15 minutes
        $schedule->command('seo:check-stalled --threshold=10')
            ->everyFifteenMinutes()
            ->withoutOverlapping(3)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
