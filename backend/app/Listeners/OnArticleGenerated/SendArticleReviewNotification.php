<?php

namespace App\Listeners\OnArticleGenerated;

use App\Enums\NotificationType;
use App\Events\ArticleGenerated;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendArticleReviewNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notifications) {}

    public function handle(ArticleGenerated $event): void
    {
        $article = $event->article->loadMissing('keyword');
        $previewUrl = url('/articles/' . $article->id);

        $this->notifications->send(
            tenantId: $article->tenant_id,
            type: NotificationType::ARTICLE_REVIEW_REQUESTED,
            subject: 'Bài viết AI cần duyệt: ' . $article->title,
            message: "Bài viết cho keyword \"{$article->keyword?->keyword}\" đã được tạo và cần duyệt.\n\nXem trong dashboard: {$previewUrl}",
            data: [
                'article_id' => $article->id,
                'keyword_id' => $article->keyword_id,
                'preview_url' => $previewUrl,
            ],
            userId: $article->user_id
        );
    }
}
