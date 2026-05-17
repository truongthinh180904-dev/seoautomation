<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ApiRateLimiter::class,
            'throttle:api',
        ]);
        
        $middleware->statefulApi();
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Process keywords due for AI pipeline — every 5 minutes
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

