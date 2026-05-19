<?php

namespace App\Jobs\AI;

use App\Agents\QAValidationAgent;
use App\Models\Article;
use App\Services\SEO\ArticleQualityReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QAValidationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [60, 180];

    public function __construct(public int $articleId)
    {
        $this->onQueue('ai-writing');
    }

    public function handle(QAValidationAgent $agent, ArticleQualityReportService $qualityReportService): void
    {
        $article = Article::with('keyword')->find($this->articleId);

        if (!$article) {
            return;
        }

        $keyword = $article->keyword->keyword ?? '';
        $wordCount = $article->word_count ?? 0;
        $density = 0;
        
        $content = mb_strtolower(strip_tags($article->content ?? ''));
        if (!empty($keyword) && $wordCount > 0) {
            $keywordCount = substr_count($content, mb_strtolower($keyword));
            $density = ($keywordCount / $wordCount) * 100;
        }

        $articleData = [
            'keyword' => $keyword,
            'title' => $article->title,
            'seo_title' => $article->seo_title,
            'seo_description' => $article->seo_description,
            'seo_description_length' => mb_strlen($article->seo_description ?? ''),
            'word_count' => $wordCount,
            'keyword_density_percentage' => round($density, 2),
            'has_faqs' => !empty($article->faqs),
            'content_excerpt' => mb_substr($content, 0, 1000) . '...',
        ];

        $context = [
            'article_data' => $articleData,
            'tenant_id' => $article->tenant_id,
            'article_id' => $article->id,
            'keyword_id' => $article->keyword_id,
        ];

        $result = $agent->execute($context);

        if ($result->success) {
            $data = $result->data;
            $report = $data['qa_report'] ?? [];
            
            if (isset($data['score']) && is_numeric($data['score'])) {
                // Combine algorithmic score with AI score (average or override)
                // We use AI override here
                $article->seo_score = $data['score'];
                $article->save();
            }

            $qualityReportService->generate($article->fresh(['keyword', 'wordpressSite']));

            Log::info("QA Validation Report for Article {$article->id}: " . json_encode($report));
        } else {
            Log::warning("QA Validation Agent failed for Article {$article->id}: " . $result->error);
        }
    }
}
