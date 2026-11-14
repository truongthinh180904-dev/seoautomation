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

        // P3.8.1: Daily Google Search Console sync — 02:00 AM
        $schedule->command('seo:sync-all --service=gsc')
            ->dailyAt('02:00')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // P3.8.2: Daily Google Analytics 4 sync — 03:00 AM
        $schedule->command('seo:sync-all --service=ga4')
            ->dailyAt('03:00')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // P3.8.3: Daily Serper Rank Tracking — 04:00 AM
        $schedule->command('seo:track-rankings')
            ->dailyAt('04:00')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // P3.8.4: Daily SEO Intelligence Anomaly Scan — 05:00 AM
        $schedule->command('seo:detect-anomalies')
            ->dailyAt('05:00')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));
    }

    /**
     * Public helper to allow feature tests to inspect registered schedule events.
     */
    public function defineSchedule(Schedule $schedule): void
    {
        $this->schedule($schedule);
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
