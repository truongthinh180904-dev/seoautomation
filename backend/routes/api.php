<?php

use App\Http\Controllers\Api\V1\AIPromptController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\KeywordController;
use App\Http\Controllers\Api\V1\MediaAssetController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\QueueStatusController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\WordPressSiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth (no throttle beyond global api)
    Route::post('/auth/login', LoginController::class)->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', LogoutController::class);
        Route::get('/auth/me', MeController::class);

        // WordPress Sites — standard API rate limit
        Route::apiResource('wordpress-sites', WordPressSiteController::class);
        Route::post('wordpress-sites/{id}/test', [WordPressSiteController::class, 'test']);

        // Campaigns — SEO campaign management
        Route::get('campaigns/{id}/stats', [CampaignController::class, 'stats']);
        Route::post('campaigns/{id}/start', [CampaignController::class, 'start']);
        Route::post('campaigns/{id}/pause', [CampaignController::class, 'pause']);
        Route::post('campaigns/{id}/resume', [CampaignController::class, 'resume']);
        Route::apiResource('campaigns', CampaignController::class);

        // Media assets — campaign image pipeline
        Route::get('media-assets', [MediaAssetController::class, 'index']);
        Route::post('media-assets', [MediaAssetController::class, 'store']);
        Route::put('media-assets/{id}', [MediaAssetController::class, 'update']);
        Route::post('media-assets/{id}/retry-download', [MediaAssetController::class, 'retryDownload']);
        Route::post('media-assets/{id}/upload-wordpress', [MediaAssetController::class, 'uploadToWordPress']);

        // Notifications — generic email/telegram/dashboard flow
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

        // Keywords — standard API rate limit
        Route::post('keywords/import/preview', [KeywordController::class, 'importPreview']);
        Route::post('keywords/import', [KeywordController::class, 'import']);
        Route::post('keywords/bulk-destroy', [KeywordController::class, 'bulkDestroy']);
        Route::apiResource('keywords', KeywordController::class);

        // Articles — standard API rate limit
        Route::get('articles', [ArticleController::class, 'index']);
        Route::get('articles/{id}', [ArticleController::class, 'show']);
        Route::put('articles/{id}', [ArticleController::class, 'update']);
        Route::delete('articles/{id}', [ArticleController::class, 'destroy']);

        Route::post('articles/{id}/retry', [ArticleController::class, 'retry']);
        Route::post('articles/{id}/auto-fix', [ArticleController::class, 'autoFix']);
        Route::post('articles/{id}/generate-images', [ArticleController::class, 'generateImages']);
        Route::post('articles/{id}/approve-publish', [ArticleController::class, 'approveAndPublish']);

        // AI Generation endpoints — 50 req/hour per tenant
        Route::middleware('throttle:ai_generation')->group(function () {
            Route::post('articles/generate', [ArticleController::class, 'generate']);
        });

        // SERP — 10 req/min globally
        Route::middleware('throttle:serp')->group(function () {
            Route::post('keywords/{id}/serp', [KeywordController::class, 'serp']);
        });

        // Article review action (approve/reject via token — public, no auth)
        // Schedules
        Route::get('schedules', [ScheduleController::class, 'index']);
        Route::post('schedules', [ScheduleController::class, 'store']);
        Route::put('schedules/{id}', [ScheduleController::class, 'update']);
        Route::delete('schedules/{id}', [ScheduleController::class, 'destroy']);

        // Queue status (powered by Horizon)
        Route::get('queue/status', [QueueStatusController::class, 'index']);
        Route::post('queue/retry/{jobId}', [QueueStatusController::class, 'retry']);

        // Analytics
        Route::get('analytics/summary', [AnalyticsController::class, 'summary']);
        Route::get('analytics/ai-costs', [AnalyticsController::class, 'aiCosts']);
        Route::get('analytics/keywords-daily', [AnalyticsController::class, 'keywordsDaily']);
        Route::get('analytics/failing-agents', [AnalyticsController::class, 'failingAgents']);
        Route::get('analytics/usage', [AnalyticsController::class, 'usage']);

        // AI Prompts (Admin/A/B Testing)
        Route::apiResource('ai-prompts', AIPromptController::class);
        Route::post('ai-prompts/{id}/activate', [AIPromptController::class, 'activate']);
        Route::post('ai-prompts/{id}/performance', [AIPromptController::class, 'updatePerformance']);
    });

    // Public article review — no auth, standard IP-based throttle
    Route::get('articles/review/{token}', [ArticleController::class, 'reviewByToken']);
    Route::post('articles/review/{token}/action', [ArticleController::class, 'reviewAction']);
});
