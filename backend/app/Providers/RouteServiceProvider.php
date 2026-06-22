<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai_generation', function (Request $request) {
            $tenantId = $request->user()?->tenant_id ?: $request->ip();
            return Limit::perHour(50)->by($tenantId);
        });

        RateLimiter::for('serp', function (Request $request) {
            return Limit::perMinute(10); // 10 req/min globally
        });
    }
}
