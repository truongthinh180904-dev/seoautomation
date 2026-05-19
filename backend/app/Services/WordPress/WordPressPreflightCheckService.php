<?php

namespace App\Services\WordPress;

use App\Models\Article;
use App\Models\WordPressSite;

class WordPressPreflightCheckService
{
    public function __construct(
        protected WordPressPublishService $publishService
    ) {}

    public function runAll(Article $article, WordPressSite $site): array
    {
        $checks = [
            $this->checkContentNotEmpty($article),
            $this->checkConnection($site),
            $this->checkSeoScoreThreshold($article),
            $this->checkMediaReady($article),
        ];

        return [
            'passed' => collect($checks)->every(fn ($check) => $check['passed']),
            'checks' => $checks,
        ];
    }

    protected function checkConnection(WordPressSite $site): array
    {
        return [
            'key' => 'wordpress_connection',
            'passed' => $this->publishService->testConnection($site),
            'message' => 'WordPress connection/auth check',
        ];
    }

    protected function checkContentNotEmpty(Article $article): array
    {
        return [
            'key' => 'content_not_empty',
            'passed' => trim(strip_tags((string) $article->content)) !== '',
            'message' => 'Article content is not empty',
        ];
    }

    protected function checkSeoScoreThreshold(Article $article): array
    {
        $minScore = (int) config('quality.min_seo_score', 70);

        return [
            'key' => 'seo_score_threshold',
            'passed' => $article->seo_score === null || $article->seo_score >= $minScore,
            'message' => "SEO score is at least {$minScore} or not scored yet",
        ];
    }

    protected function checkMediaReady(Article $article): array
    {
        $required = (bool) config('quality.featured_image_required', false);

        return [
            'key' => 'featured_image',
            'passed' => !$required || filled($article->featured_image_url) || filled($article->image_assets),
            'message' => 'Featured image is available when required',
        ];
    }
}
