<?php

namespace App\Listeners\OnArticleApproved;

use App\Events\ArticleApproved;
use App\Jobs\Publishing\PublishToWordPressJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class DispatchPublishingJob implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'publishing';
    public int $tries = 2;
    public array $backoff = [60, 300];

    public function handle(ArticleApproved $event): void
    {
        $article = $event->article;

        if (!$article->wordpress_site_id) {
            Log::warning("Article #{$article->id} approved but has no WordPress site. Skipping publish.");
            return;
        }

        PublishToWordPressJob::dispatch($article->id, $article->wordpress_site_id);
        Log::info("PublishToWordPressJob dispatched for article #{$article->id}");
    }
}
