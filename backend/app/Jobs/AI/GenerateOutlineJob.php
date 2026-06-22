<?php

namespace App\Jobs\AI;

use App\Agents\OutlineAgent;
use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Models\Article;
use App\Models\CompetitorAnalysis;
use App\Models\Keyword;
use App\Services\Article\ArticlePipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateOutlineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [60, 180];

    public function __construct(
        public int $keywordId,
        public int $articleId
    ) {
        $this->onQueue('ai-writing');
    }

    public function handle(OutlineAgent $agent, ArticlePipelineService $pipeline): void
    {
        $article = Article::find($this->articleId);
        $keyword = Keyword::find($this->keywordId);

        if (!$article || !$keyword) {
            return;
        }

        $pipeline->complete($article, 'queued', 'Queue worker đã bắt đầu xử lý.');
        $pipeline->start($article, 'outline', 'AI đang tạo dàn ý bài viết.');

        $competitorAnalyses = CompetitorAnalysis::where('keyword_id', $this->keywordId)
            ->where('analysis_completed', true)
            ->get();

        $context = [
            'keyword' => $keyword->keyword,
            'search_intent' => $keyword->search_intent ?: 'informational',
            'target_word_count' => $keyword->target_word_count ?: 3000,
            'competitor_data' => $competitorAnalyses->toArray(),
            'keyword_id' => $this->keywordId,
            'tenant_id' => $article->tenant_id,
            'article_id' => $this->articleId,
        ];

        $result = $agent->execute($context);

        if (!$result->success) {
            $pipeline->fail($article, 'outline', $result->error ?? 'Outline generation failed.');
            $article->update(['status' => 'failed', 'review_notes' => 'Outline generation failed: ' . $result->error]);
            $keyword->update(['status' => KeywordStatus::FAILED]);
            Log::error("GenerateOutlineJob failed: " . $result->error);
            return;
        }

        $article->update([
            'outline' => $result->data['outline'],
        ]);
        $pipeline->complete($article->fresh(), 'outline', 'Đã tạo dàn ý xong.');

        GenerateArticleJob::dispatch($this->keywordId, $this->articleId)->onQueue('ai-writing');
    }

    public function failed(Throwable $exception): void
    {
        if ($article = Article::find($this->articleId)) {
            app(ArticlePipelineService::class)->fail($article, 'outline', $exception->getMessage());
        }

        Article::whereKey($this->articleId)->update([
            'status' => ArticleStatus::FAILED,
            'review_notes' => 'Outline generation failed: ' . $exception->getMessage(),
        ]);

        Keyword::whereKey($this->keywordId)->update([
            'status' => KeywordStatus::FAILED,
        ]);
    }
}
