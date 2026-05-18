<?php

namespace App\Jobs\AI;

use App\Agents\OutlineAgent;
use App\Enums\KeywordStatus;
use App\Models\Article;
use App\Models\CompetitorAnalysis;
use App\Models\Keyword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

    public function handle(OutlineAgent $agent): void
    {
        $article = Article::find($this->articleId);
        $keyword = Keyword::find($this->keywordId);

        if (!$article || !$keyword) {
            return;
        }

        $competitorAnalyses = CompetitorAnalysis::where('keyword_id', $this->keywordId)
            ->where('analysis_completed', true)
            ->get();

        $context = [
            'keyword' => $keyword->keyword,
            'search_intent' => 'informational', // Could be dynamic from DB
            'competitor_data' => $competitorAnalyses->toArray(),
            'keyword_id' => $this->keywordId,
            'tenant_id' => $article->tenant_id,
            'article_id' => $this->articleId,
        ];

        $result = $agent->execute($context);

        if (!$result->success) {
            $article->update(['status' => 'failed', 'review_notes' => 'Outline generation failed: ' . $result->error]);
            $keyword->update(['status' => KeywordStatus::FAILED]);
            Log::error("GenerateOutlineJob failed: " . $result->error);
            return;
        }

        $article->update([
            'outline' => $result->data['outline'],
        ]);

        GenerateArticleJob::dispatch($this->keywordId, $this->articleId)->onQueue('ai-writing');
    }
}
