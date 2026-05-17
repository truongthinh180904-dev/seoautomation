<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;

Route::prefix('v1')->group(function () {

    // Auth (no throttle beyond global api)
    Route::post('/auth/login', LoginController::class)->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', LogoutController::class);
        Route::get('/auth/me', MeController::class);

        // WordPress Sites — standard API rate limit
        Route::apiResource('wordpress-sites', \App\Http\Controllers\Api\V1\WordPressSiteController::class);
        Route::post('wordpress-sites/{id}/test', [\App\Http\Controllers\Api\V1\WordPressSiteController::class, 'test']);

        // Keywords — standard API rate limit
        Route::post('keywords/import', [\App\Http\Controllers\Api\V1\KeywordController::class, 'import']);
        Route::post('keywords/bulk-destroy', [\App\Http\Controllers\Api\V1\KeywordController::class, 'bulkDestroy']);
        Route::apiResource('keywords', \App\Http\Controllers\Api\V1\KeywordController::class);

        // Articles — standard API rate limit
        Route::get('articles', [\App\Http\Controllers\Api\V1\ArticleController::class, 'index']);
        Route::get('articles/{id}', [\App\Http\Controllers\Api\V1\ArticleController::class, 'show']);
        Route::put('articles/{id}', [\App\Http\Controllers\Api\V1\ArticleController::class, 'update']);
        Route::delete('articles/{id}', [\App\Http\Controllers\Api\V1\ArticleController::class, 'destroy']);

        Route::post('articles/{id}/retry', [\App\Http\Controllers\Api\V1\ArticleController::class, 'retry']);

        // AI Generation endpoints — 50 req/hour per tenant
        Route::middleware('throttle:ai_generation')->group(function () {
            Route::post('articles/generate', [\App\Http\Controllers\Api\V1\ArticleController::class, 'generate']);
        });

        // SERP — 10 req/min globally
        Route::middleware('throttle:serp')->group(function () {
            Route::post('keywords/{id}/serp', [\App\Http\Controllers\Api\V1\KeywordController::class, 'serp']);
        });

        // Article review action (approve/reject via token — public, no auth)
        // Schedules
        Route::get('schedules', [\App\Http\Controllers\Api\V1\ScheduleController::class, 'index']);
        Route::post('schedules', [\App\Http\Controllers\Api\V1\ScheduleController::class, 'store']);
        Route::put('schedules/{keywordId}', [\App\Http\Controllers\Api\V1\ScheduleController::class, 'update']);
        Route::delete('schedules/{keywordId}', [\App\Http\Controllers\Api\V1\ScheduleController::class, 'destroy']);

        // Queue status (powered by Horizon)
        Route::get('queue/status', [\App\Http\Controllers\Api\V1\QueueStatusController::class, 'index']);
        Route::post('queue/retry/{jobId}', [\App\Http\Controllers\Api\V1\QueueStatusController::class, 'retry']);

        // Analytics
        Route::get('analytics/summary', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'summary']);
        Route::get('analytics/ai-costs', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'aiCosts']);
        Route::get('analytics/keywords-daily', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'keywordsDaily']);
        Route::get('analytics/failing-agents', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'failingAgents']);

        // AI Prompts (Admin/A/B Testing)
        Route::apiResource('ai-prompts', \App\Http\Controllers\Api\V1\AIPromptController::class);
        Route::post('ai-prompts/{id}/activate', [\App\Http\Controllers\Api\V1\AIPromptController::class, 'activate']);
        Route::post('ai-prompts/{id}/performance', [\App\Http\Controllers\Api\V1\AIPromptController::class, 'updatePerformance']);
    });

    // Public article review — no auth, standard IP-based throttle
    Route::get('articles/review/{token}', [\App\Http\Controllers\Api\V1\ArticleController::class, 'reviewByToken']);
    Route::post('articles/review/{token}/action', [\App\Http\Controllers\Api\V1\ArticleController::class, 'reviewAction']);
});
