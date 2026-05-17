<?php

namespace App\Listeners\OnArticlePublished;

use App\Enums\ArticleStatus;
use App\Events\ArticlePublished;
use App\Models\PublishingLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdatePublishingLog implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'publishing';
    public int $tries = 3;

    public function handle(ArticlePublished $event): void
    {
        $article = $event->article;

        try {
            PublishingLog::create([
                'article_id'          => $article->id,
                'wordpress_site_id'   => $article->wordpress_site_id,
                'tenant_id'           => $article->tenant_id,
                'status'              => 'success',
                'wordpress_post_id'   => $article->wordpress_post_id,
                'wordpress_post_url'  => $event->wordpressPostUrl,
                'published_at'        => now(),
            ]);

            Log::info("PublishingLog updated for article #{$article->id} → {$event->wordpressPostUrl}");
        } catch (\Throwable $e) {
            Log::error("UpdatePublishingLog listener failed for article #{$article->id}: " . $e->getMessage());
        }
    }
}
