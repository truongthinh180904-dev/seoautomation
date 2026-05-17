<?php

namespace App\Jobs\Notification;

use App\Models\Article;
use App\Services\Zalo\ZaloNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendZaloNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    public function __construct(public int $articleId)
    {
        $this->onQueue('default');
    }

    public function handle(ZaloNotificationService $zaloService): void
    {
        $article = Article::find($this->articleId);
        if (!$article) {
            return;
        }

        $zaloService->sendReviewNotification($article);
    }
}
