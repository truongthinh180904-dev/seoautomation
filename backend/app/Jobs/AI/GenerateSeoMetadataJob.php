<?php

namespace App\Jobs\AI;

use App\Agents\SEOOptimizationAgent;
use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Models\Article;
use App\Services\SEO\SEOScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSeoMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [60, 180];

    public function __construct(
        public int $articleId
    ) {
        $this->onQueue('ai-writing');
    }

    public function handle(SEOOptimizationAgent $agent, SEOScoreService $seoScoreService): void
    {
        $article = Article::with('keyword')->find($this->articleId);

        if (!$article || !$article->keyword) {
            return;
        }

        $context = [
            'keyword' => $article->keyword->keyword,
            'article_content' => $article->content,
            'tenant_id' => $article->tenant_id,
            'keyword_id' => $article->keyword_id,
            'article_id' => $this->articleId,
        ];

        $result = $agent->execute($context);

        if (!$result->success) {
            $article->update(['status' => ArticleStatus::FAILED, 'review_notes' => 'SEO Metadata failed: ' . $result->error]);
            $this->fail(new \Exception($result->error));
            return;
        }

        $data = $result->data;

        $article->seo_title = $data['seo_title'];
        $article->seo_description = $data['seo_description'];
        
        if (!empty($data['faqs'])) {
            $article->faqs = $data['faqs'];
        }

        $article->seo_score = $seoScoreService->calculate($article);
        $article->status = ArticleStatus::REVIEW;
        $article->generateReviewToken();
        $article->save();
        $article->keyword->update([
            'status' => KeywordStatus::COMPLETED,
            'processed_at' => now(),
        ]);

        if (class_exists('App\Jobs\Notification\SendZaloNotificationJob')) {
            \App\Jobs\Notification\SendZaloNotificationJob::dispatch($article->id)->onQueue('default');
        }
    }
}
