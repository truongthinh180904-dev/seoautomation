<?php

namespace App\Services\SEO;

use App\Models\Article;

class SEOScoreService
{
    public function calculate(Article $article): int
    {
        $score = 100;
        $keyword = mb_strtolower($article->keyword->keyword ?? '');
        $title = mb_strtolower($article->seo_title ?? $article->title ?? '');
        $content = mb_strtolower(strip_tags($article->content ?? ''));
        $description = $article->seo_description ?? '';
        $wordCount = $article->word_count ?? 0;

        // Keyword in title
        if (!empty($keyword) && !str_contains($title, $keyword)) {
            $score -= 20;
        }

        // Meta length
        $titleLength = mb_strlen($article->seo_title ?? '');
        if ($titleLength < 30 || $titleLength > 65) {
            $score -= 10;
        }

        $descLength = mb_strlen($description);
        if ($descLength < 120 || $descLength > 165) {
            $score -= 10;
        }

        // Word count
        if ($wordCount < 1000) {
            $score -= 20;
        } elseif ($wordCount < 1500) {
            $score -= 10;
        }

        // Keyword density
        if (!empty($keyword) && !empty($content)) {
            $keywordCount = substr_count($content, $keyword);
            if ($wordCount > 0) {
                $density = ($keywordCount / $wordCount) * 100;
                if ($density < 0.5) {
                    $score -= 15;
                } elseif ($density > 2.5) {
                    $score -= 15; // Keyword stuffing penalty
                }
            }
        }

        return max(0, min(100, $score));
    }
}
