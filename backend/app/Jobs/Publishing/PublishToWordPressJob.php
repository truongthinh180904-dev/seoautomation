<?php

namespace App\Jobs\Publishing;

use App\DTOs\PublishingDTO;
use App\Enums\ArticleStatus;
use App\Jobs\Notification\SendZaloNotificationJob;
use App\Models\Article;
use App\Models\PublishingLog;
use App\Services\WordPress\WordPressPublishService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishToWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300, 600];
    public $timeout = 120;

    public function __construct(
        public int $articleId,
        public int $wordpressSiteId
    ) {
        $this->onQueue('publishing');
    }

    public function handle(WordPressPublishService $publishService): void
    {
        $startTime = microtime(true);
        $article = Article::with('wordpressSite')->find($this->articleId);

        if (!$article) {
            return;
        }

        if ($article->status !== ArticleStatus::APPROVED) {
            Log::warning("PublishToWordPressJob aborted: Article {$article->id} is not APPROVED (status: {$article->status->value})");
            return;
        }

        $site = $article->wordpressSite ?? \App\Models\WordPressSite::find($this->wordpressSiteId);
        
        if (!$site) {
            Log::warning("PublishToWordPressJob aborted: No WordPress site configured for article {$article->id}");
            return;
        }

        $article->update(['status' => ArticleStatus::PUBLISHING]);

        $dto = new PublishingDTO(
            title: $article->title,
            content: $article->content,
            publishStatus: $article->scheduled_publish_at ? 'future' : 'publish',
            categoryIds: [], // Depending on implementation, you can map categories
            tagIds: [],
            seoTitle: $article->seo_title,
            seoDescription: $article->seo_description,
            scheduledAt: $article->scheduled_publish_at ? $article->scheduled_publish_at->toIso8601String() : null
        );

        try {
            $result = $publishService->publish($dto, $site);

            $article->update([
                'status' => ArticleStatus::PUBLISHED,
                'wordpress_post_id' => $result['post_id'],
                'wordpress_post_url' => $result['url'],
                'published_at' => now(),
            ]);

            PublishingLog::create([
                'tenant_id' => $article->tenant_id,
                'article_id' => $article->id,
                'wordpress_site_id' => $site->id,
                'wordpress_post_id' => $result['post_id'],
                'attempt_number' => $this->attempts(),
                'status' => 'success',
                'http_status_code' => 200, // or 201
                'published_url' => $result['url'],
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ]);

            if (class_exists(SendZaloNotificationJob::class)) {
                // SendZaloNotificationJob::dispatch($article->id)->onQueue('default');
            }

        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function failed(Throwable $exception): void
    {
        $article = Article::find($this->articleId);
        if ($article) {
            $article->update(['status' => ArticleStatus::FAILED, 'review_notes' => 'Publishing failed: ' . $exception->getMessage()]);
            
            PublishingLog::create([
                'tenant_id' => $article->tenant_id,
                'article_id' => $article->id,
                'wordpress_site_id' => $this->wordpressSiteId,
                'attempt_number' => $this->attempts(),
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'duration_ms' => 0,
            ]);

            if (class_exists(SendZaloNotificationJob::class)) {
                // Could dispatch failure notification here
                // SendZaloNotificationJob::dispatch($article->id)->onQueue('default');
            }
        }
    }
}
