<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class CampaignStatsService
{
    public function getStats(Campaign $campaign): array
    {
        $keywordCounts = $campaign->keywords()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $articleCounts = $campaign->articles()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $articleQuery = $campaign->articles();
        $totalArticles = (clone $articleQuery)->count();
        $articlesWithMedia = (clone $articleQuery)
            ->where(function ($query) {
                $query->whereNotNull('featured_image_url')
                    ->orWhereNotNull('image_assets');
            })
            ->count();
        $articlesWithInternalLinks = (clone $articleQuery)
            ->whereNotNull('internal_links')
            ->count();

        return [
            'keywords' => [
                'total' => array_sum($keywordCounts),
                'by_status' => $keywordCounts,
            ],
            'articles' => [
                'total' => $totalArticles,
                'by_status' => $articleCounts,
                'generated' => $totalArticles,
                'in_review' => $articleCounts['review'] ?? 0,
                'approved' => $articleCounts['approved'] ?? 0,
                'published' => $articleCounts['published'] ?? 0,
                'failed' => $articleCounts['failed'] ?? 0,
            ],
            'cost' => [
                'ai_cost_usd' => round((float) $campaign->articles()->sum('ai_cost_usd'), 6),
                'average_seo_score' => round((float) $campaign->articles()->whereNotNull('seo_score')->avg('seo_score'), 2),
            ],
            'coverage' => [
                'media_percent' => $totalArticles > 0 ? round($articlesWithMedia / $totalArticles * 100, 2) : 0,
                'internal_links_percent' => $totalArticles > 0 ? round($articlesWithInternalLinks / $totalArticles * 100, 2) : 0,
            ],
            'calendar' => $campaign->articles()
                ->whereNotNull('scheduled_publish_at')
                ->orderBy('scheduled_publish_at')
                ->get(['id', 'title', 'status', 'scheduled_publish_at'])
                ->map(fn ($article) => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'status' => $article->status?->value ?? $article->status,
                    'scheduled_publish_at' => $article->scheduled_publish_at,
                ])
                ->values(),
        ];
    }
}
