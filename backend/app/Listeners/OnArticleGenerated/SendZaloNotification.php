<?php

namespace App\Listeners\OnArticleGenerated;

use App\Events\ArticleGenerated;
use App\Services\Zalo\ZaloNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendZaloNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(protected ZaloNotificationService $zaloService) {}

    public function handle(ArticleGenerated $event): void
    {
        $article = $event->article;

        try {
            $article->loadMissing(['keyword', 'tenant']);
            $this->zaloService->sendReviewNotification($article);
            Log::info("Zalo review notification sent for article #{$article->id}");
        } catch (\Throwable $e) {
            Log::error("SendZaloNotification failed for article #{$article->id}: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
