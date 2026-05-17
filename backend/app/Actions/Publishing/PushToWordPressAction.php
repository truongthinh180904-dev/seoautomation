<?php

namespace App\Actions\Publishing;

use App\Enums\ArticleStatus;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Models\Article;
use Lorisleiva\Actions\Concerns\AsAction;

class PushToWordPressAction
{
    use AsAction;

    public function handle(Article $article): void
    {
        if ($article->status !== ArticleStatus::APPROVED) {
            throw new \InvalidArgumentException("Article status must be approved before publishing.");
        }

        $wordpressSiteId = $article->wordpress_site_id;
        
        if (!$wordpressSiteId) {
            throw new \RuntimeException("No WordPress site assigned to this article.");
        }

        PublishToWordPressJob::dispatch($article->id, $wordpressSiteId)
            ->onQueue('publishing');
    }
}
