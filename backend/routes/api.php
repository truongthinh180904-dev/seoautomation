<?php

use App\Http\Controllers\Api\V1\Admin\AdminCreditAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\AdminPaymentOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminWorkspaceController;
use App\Http\Controllers\Api\V1\AIPromptController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Billing\CreditController;
use App\Http\Controllers\Api\V1\Billing\PaymentOrderController;
use App\Http\Controllers\Api\V1\Billing\PlanController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Http\Controllers\Api\V1\BrandKnowledgeController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\CompetitorAnalysisController;
use App\Http\Controllers\Api\V1\ContentGapController;
use App\Http\Controllers\Api\V1\ContentStudioController;
use App\Http\Controllers\Api\V1\InternalLinkController;
use App\Http\Controllers\Api\V1\KeywordController;
use App\Http\Controllers\Api\V1\KeywordResearchController;
use App\Http\Controllers\Api\V1\MediaAssetController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\QueueStatusController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SeoScoringController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TopicClusterController;
use App\Http\Controllers\Api\V1\WebsiteController;
use App\Http\Controllers\Api\V1\WordPressSiteController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('serp', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('ai_generation', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});

Route::prefix('v1')->group(function () {

    // Public Authentication routes
    Route::post('/auth/register', RegisterController::class)->name('register');
    Route::post('/auth/login', LoginController::class)->name('login');
    Route::post('/auth/forgot-password', ForgotPasswordController::class)->name('password.forgot');
    Route::post('/auth/reset-password', ResetPasswordController::class)->name('password.reset');

    // Authenticated routes
    Route::middleware(['auth:sanctum', 'active_account'])->group(function () {
        Route::post('/auth/logout', LogoutController::class);
        Route::get('/auth/me', MeController::class);

        // Workspaces Management
        Route::get('workspaces', [WorkspaceController::class, 'index']);
        Route::post('workspaces', [WorkspaceController::class, 'store']);
        Route::get('workspaces/{id}', [WorkspaceController::class, 'show']);
        Route::post('workspaces/{id}/switch', [WorkspaceController::class, 'switchWorkspace']);
        Route::get('workspaces/{id}/members', [WorkspaceController::class, 'members']);
        Route::post('workspaces/{id}/members', [WorkspaceController::class, 'addMember']);
        Route::delete('workspaces/{id}/members/{userId}', [WorkspaceController::class, 'removeMember']);

        // Workspace-scoped domain routes
        Route::middleware('workspace')->group(function () {
            // Websites Management
            Route::apiResource('websites', WebsiteController::class);

            // WordPress Sites
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

            // Notifications
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

            // Keywords
            Route::get('keywords/research', [KeywordResearchController::class, 'research']);
            Route::post('keywords/research', [KeywordResearchController::class, 'research']);
            Route::post('keywords/cluster', [KeywordResearchController::class, 'cluster']);
            Route::post('keywords/research/save', [KeywordResearchController::class, 'saveToCampaign']);
            Route::post('keywords/import/preview', [KeywordController::class, 'importPreview']);
            Route::post('keywords/import', [KeywordController::class, 'import']);
            Route::post('keywords/bulk-destroy', [KeywordController::class, 'bulkDestroy']);
            Route::apiResource('keywords', KeywordController::class);

            // Topic Clusters
            Route::get('topic-clusters', [TopicClusterController::class, 'index']);
            Route::post('topic-clusters', [TopicClusterController::class, 'store']);
            Route::post('topic-clusters/generate-ai', [TopicClusterController::class, 'generateAi']);
            Route::get('topic-clusters/{id}', [TopicClusterController::class, 'show']);
            Route::put('topic-clusters/{id}', [TopicClusterController::class, 'update']);
            Route::delete('topic-clusters/{id}', [TopicClusterController::class, 'destroy']);
            Route::post('topic-clusters/{id}/assign-keywords', [TopicClusterController::class, 'assignKeywords']);
            Route::post('topic-clusters/{id}/remove-keyword', [TopicClusterController::class, 'removeKeyword']);

            // Competitor & SERP Intelligence
            Route::get('competitor/serp-analysis', [CompetitorAnalysisController::class, 'serpAnalysis']);
            Route::post('competitor/serp-analysis', [CompetitorAnalysisController::class, 'serpAnalysis']);
            Route::get('competitor/content-gaps', [ContentGapController::class, 'index']);
            Route::post('competitor/content-gaps/analyze', [ContentGapController::class, 'analyze']);
            Route::post('competitor/content-gaps/{id}/target', [ContentGapController::class, 'target']);
            Route::post('competitor/content-gaps/{id}/ignore', [ContentGapController::class, 'ignore']);
            Route::delete('competitor/content-gaps/{id}', [ContentGapController::class, 'destroy']);

            // Brand Knowledge Base
            Route::get('brand-knowledge', [BrandKnowledgeController::class, 'index']);
            Route::post('brand-knowledge', [BrandKnowledgeController::class, 'store']);
            Route::post('brand-knowledge/inject-context', [BrandKnowledgeController::class, 'injectContext']);
            Route::post('brand-knowledge/validate-taboo', [BrandKnowledgeController::class, 'validateTaboo']);
            Route::get('brand-knowledge/{id}', [BrandKnowledgeController::class, 'show']);
            Route::put('brand-knowledge/{id}', [BrandKnowledgeController::class, 'update']);
            Route::delete('brand-knowledge/{id}', [BrandKnowledgeController::class, 'destroy']);
            Route::post('brand-knowledge/{id}/set-default', [BrandKnowledgeController::class, 'setDefault']);

            // AI Content Studio
            Route::get('content-studio/{id}', [ContentStudioController::class, 'show']);
            Route::get('content-studio/{id}/versions', [ContentStudioController::class, 'versions']);
            Route::post('content-studio/assist', [ContentStudioController::class, 'assist']);
            Route::post('content-studio/autosave', [ContentStudioController::class, 'autosave']);

            // Internal Linking Intelligence & AI Images
            Route::get('internal-links', [InternalLinkController::class, 'index']);
            Route::get('internal-links/graph', [InternalLinkController::class, 'graph']);
            Route::get('internal-links/orphans', [InternalLinkController::class, 'orphans']);
            Route::post('internal-links/scan', [InternalLinkController::class, 'scan']);
            Route::post('internal-links/{id}/accept', [InternalLinkController::class, 'accept']);
            Route::post('internal-links/{id}/reject', [InternalLinkController::class, 'reject']);
            Route::post('internal-links/{id}/apply', [InternalLinkController::class, 'apply']);
            Route::post('internal-links/generate-image', [InternalLinkController::class, 'generateImage']);

            // Articles
            Route::get('articles', [ArticleController::class, 'index']);
            Route::get('articles/{id}', [ArticleController::class, 'show']);
            Route::put('articles/{id}', [ArticleController::class, 'update']);
            Route::delete('articles/{id}', [ArticleController::class, 'destroy']);

            Route::post('articles/{id}/retry', [ArticleController::class, 'retry']);
            Route::post('articles/{id}/auto-fix', [ArticleController::class, 'autoFix']);
            Route::post('articles/{id}/score-audit', [SeoScoringController::class, 'audit']);
            Route::post('articles/score-audit', [SeoScoringController::class, 'auditLive']);
            Route::post('articles/{id}/images', [ArticleController::class, 'generateImages']);
            Route::post('articles/{id}/generate-images', [ArticleController::class, 'generateImages']);
            Route::post('articles/{id}/approve-publish', [ArticleController::class, 'approveAndPublish']);

            // AI Generation endpoints
            Route::middleware('throttle:ai_generation')->group(function () {
                Route::post('articles/generate', [ArticleController::class, 'generate']);
            });

            // SERP lookups
            Route::middleware('throttle:serp')->group(function () {
                Route::post('keywords/{id}/serp', [KeywordController::class, 'serp']);
            });

            // Tasks & Background Pipeline Execution
            Route::get('tasks', [TaskController::class, 'index']);
            Route::get('tasks/{id}', [TaskController::class, 'show']);
            Route::post('tasks/{id}/cancel', [TaskController::class, 'cancel']);

            // Schedules
            Route::get('schedules', [ScheduleController::class, 'index']);
            Route::post('schedules', [ScheduleController::class, 'store']);
            Route::put('schedules/{id}', [ScheduleController::class, 'update']);
            Route::delete('schedules/{id}', [ScheduleController::class, 'destroy']);

            // Analytics
            Route::get('analytics/summary', [AnalyticsController::class, 'summary']);
            Route::get('analytics/ai-costs', [AnalyticsController::class, 'aiCosts']);
            Route::get('analytics/keywords-daily', [AnalyticsController::class, 'keywordsDaily']);
            Route::get('analytics/failing-agents', [AnalyticsController::class, 'failingAgents']);
            Route::get('analytics/usage', [AnalyticsController::class, 'usage']);

            // AI Prompts
            Route::apiResource('ai-prompts', AIPromptController::class);
            Route::post('ai-prompts/{id}/activate', [AIPromptController::class, 'activate']);
            Route::post('ai-prompts/{id}/performance', [AIPromptController::class, 'updatePerformance']);

            // Billing, Subscription & Credit
            Route::get('billing/plans', [PlanController::class, 'index']);
            Route::get('billing/subscription', [SubscriptionController::class, 'current']);
            Route::get('billing/orders', [PaymentOrderController::class, 'index']);
            Route::post('billing/orders', [PaymentOrderController::class, 'store']);
            Route::get('billing/orders/{id}', [PaymentOrderController::class, 'show']);
            Route::post('billing/orders/{id}/proof', [PaymentOrderController::class, 'submitProof']);
            Route::post('billing/orders/{id}/cancel', [PaymentOrderController::class, 'cancel']);
            Route::get('billing/credit-history', [CreditController::class, 'history']);
            Route::get('billing/balance', [CreditController::class, 'balance']);
        });

        // Platform Admin Operations (Queue status backwards compatibility)
        Route::middleware('admin')->group(function () {
            Route::get('queue/status', [QueueStatusController::class, 'index']);
            Route::post('queue/retry/{jobId}', [QueueStatusController::class, 'retry']);
        });

        // Platform Admin Operations (Admin Portal prefix)
        Route::middleware('admin')->prefix('admin')->group(function () {
            // Queue status
            Route::get('queue/status', [QueueStatusController::class, 'index']);
            Route::post('queue/retry/{jobId}', [QueueStatusController::class, 'retry']);

            // Payment Orders Review & Verification
            Route::get('payment-orders', [AdminPaymentOrderController::class, 'index']);
            Route::get('payment-orders/{id}', [AdminPaymentOrderController::class, 'show']);
            Route::post('payment-orders/{id}/approve', [AdminPaymentOrderController::class, 'approve']);
            Route::post('payment-orders/{id}/reject', [AdminPaymentOrderController::class, 'reject']);

            // Workspaces Management & Credit Adjustment
            Route::get('workspaces', [AdminWorkspaceController::class, 'index']);
            Route::get('workspaces/{id}', [AdminWorkspaceController::class, 'show']);
            Route::post('workspaces/{id}/credit-adjust', [AdminCreditAdjustmentController::class, 'adjust']);
            Route::post('workspaces/{id}/status', [AdminWorkspaceController::class, 'updateStatus']);
        });
    });

    // Public article review — no auth, standard token-based review
    Route::get('articles/review/{token}', [ArticleController::class, 'reviewByToken']);
    Route::post('articles/review/{token}/action', [ArticleController::class, 'reviewAction']);
});
