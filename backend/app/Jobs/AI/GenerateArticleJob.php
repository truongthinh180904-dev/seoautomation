<?php

namespace App\Jobs\AI;

use App\Agents\WritingAgent;
use App\Enums\ArticleStatus;
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
use Throwable;

class GenerateArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [120, 300];

    public function __construct(
        public int $keywordId,
        public int $articleId
    ) {
        $this->onQueue('ai-writing');
    }

    public function handle(WritingAgent $agent): void
    {
        $article = Article::find($this->articleId);
        $keyword = Keyword::find($this->keywordId);

        if (!$article || !$keyword) {
            return;
        }

        $competitorAnalyses = CompetitorAnalysis::where('keyword_id', $this->keywordId)
            ->where('analysis_completed', true)
            ->get();

        $semanticKeywords = [];
        $entities = [];
        $faqs = [];

        foreach ($competitorAnalyses as $comp) {
            if (is_array($comp->semantic_keywords)) {
                $semanticKeywords = array_merge($semanticKeywords, $comp->semantic_keywords);
            }
            if (is_array($comp->semantic_entities)) {
                $entities = array_merge($entities, $comp->semantic_entities);
            }
            if (is_array($comp->faqs)) {
                $faqs = array_merge($faqs, $comp->faqs);
            }
        }

        $context = [
            'keyword' => $keyword->keyword,
            'outline' => $article->outline,
            'semantic_keywords' => array_unique($semanticKeywords),
            'entities' => array_unique($entities),
            'faqs' => $faqs,
            'competitor_data' => $competitorAnalyses->toArray(),
            'article_id' => $this->articleId,
            'keyword_id' => $this->keywordId,
            'tenant_id' => $article->tenant_id,
        ];

        $result = $agent->execute($context);

        if (!$result->success) {
            $this->fail(new \Exception($result->error ?? 'WritingAgent failed'));
            return;
        }

        $data = $result->data;

        $article->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'excerpt' => $data['excerpt'],
            'word_count' => $data['word_count'],
            'ai_provider' => $data['ai_provider'] ?? 'openai',
            'ai_model' => $data['ai_model'] ?? null,
            'ai_tokens_used' => $result->tokensUsed,
            'ai_cost_usd' => $data['ai_cost_usd'] ?? 0,
            'duplicate_check_hash' => $data['duplicate_check_hash'] ?? null,
        ]);

        // Dispatch GenerateSeoMetadataJob
        if (class_exists('App\Jobs\AI\GenerateSeoMetadataJob')) {
            \App\Jobs\AI\GenerateSeoMetadataJob::dispatch($this->articleId)->onQueue('ai-writing');
        } else {
            Log::info("GenerateSeoMetadataJob not found, skipping for now.");
        }
    }

    public function failed(Throwable $exception): void
    {
        $article = Article::find($this->articleId);
        if ($article) {
            $article->update([
                'status' => ArticleStatus::FAILED,
                'review_notes' => "Writing generation failed: " . $exception->getMessage()
            ]);

            $article->keyword?->update(['status' => KeywordStatus::FAILED]);
        }
    }
}
