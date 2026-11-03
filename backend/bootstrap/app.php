<?php

use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\QuotaExceededException;
use App\Http\Middleware\ApiRateLimiter;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureWorkspaceAccess;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'throttle' => ThrottleRequests::class,
            'workspace' => EnsureWorkspaceAccess::class,
            'admin' => EnsurePlatformAdmin::class,
            'active_account' => EnsureActiveAccount::class,
        ]);

        $middleware->api(prepend: [
            ApiRateLimiter::class,
            'throttle:api',
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
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

        // Process credit grants, expiration and cycles — hourly
        $schedule->command('credit:process-cycles')
            ->hourly()
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduler.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InsufficientCreditsException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => 'INSUFFICIENT_CREDITS',
                    'required_credits' => $e->requiredCredits,
                    'available_credits' => $e->availableCredits,
                    'workspace_id' => $e->workspaceId,
                ], 402);
            }
        });

        $exceptions->render(function (QuotaExceededException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => 'QUOTA_EXCEEDED',
                    'quota_type' => $e->quotaType,
                    'limit' => $e->limit,
                    'used' => $e->used,
                    'workspace_id' => $e->workspaceId,
                ], 422);
            }
        });
    })->create();
