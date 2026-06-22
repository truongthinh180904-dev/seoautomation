<?php

namespace App\Services\SEO;

use App\Agents\SEOOptimizationAgent;
use App\Models\Article;
use Illuminate\Support\Str;

class SEOAutoFixService
{
    public function __construct(
        protected ArticleQualityReportService $qualityReportService,
        protected SEOOptimizationAgent $seoAgent
    ) {}

    public function autoFix(Article $article): Article
    {
        $article->loadMissing('keyword');
        $keyword = $article->keyword->keyword ?? $article->focus_keyword ?? $article->title;
        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags((string) $article->content)));

        $updates = [];

        $needsSeoAgent = false;
        if (blank($article->seo_title) || mb_strlen((string) $article->seo_title) < config('quality.meta_title_min')) {
            $needsSeoAgent = true;
        }
        if (blank($article->seo_description) || mb_strlen((string) $article->seo_description) < config('quality.meta_desc_min')) {
            $needsSeoAgent = true;
        }
        if (empty($article->faqs)) {
            $needsSeoAgent = true;
        }

        if ($needsSeoAgent) {
            try {
                $context = [
                    'keyword' => $keyword,
                    'article_content' => $article->content,
                    'tenant_id' => $article->tenant_id,
                    'keyword_id' => $article->keyword_id,
                    'article_id' => $article->id,
                ];
                $result = $this->seoAgent->execute($context);
                if ($result->success) {
                    $seoData = $result->data;
                    if (!empty($seoData['seo_title'])) {
                        $updates['seo_title'] = $seoData['seo_title'];
                    }
                    if (!empty($seoData['seo_description'])) {
                        $updates['seo_description'] = $seoData['seo_description'];
                    }
                    if (!empty($seoData['faqs'])) {
                        $updates['faqs'] = $seoData['faqs'];
                    }
                }
            } catch (\Throwable $e) {
                // Fail silently and fall back to static logic below
            }
        }

        // Fallbacks if AI failed or fields are still invalid/blank
        $currentTitle = $updates['seo_title'] ?? $article->seo_title;
        if (blank($currentTitle) || mb_strlen((string) $currentTitle) < config('quality.meta_title_min')) {
            $updates['seo_title'] = Str::limit($article->title ?: $keyword, config('quality.meta_title_max'), '');
        }

        $currentDesc = $updates['seo_description'] ?? $article->seo_description;
        if (blank($currentDesc) || mb_strlen((string) $currentDesc) < config('quality.meta_desc_min')) {
            $description = $plainText ?: "Tìm hiểu {$keyword}, cách triển khai hiệu quả, lưu ý quan trọng và các bước tối ưu SEO thực tế.";
            $updates['seo_description'] = Str::limit($description, config('quality.meta_desc_max'), '');
        }

        if (blank($article->slug)) {
            $updates['slug'] = Str::slug($keyword);
        }

        if (blank($article->wp_slug)) {
            $updates['wp_slug'] = Str::slug($keyword);
        }

        if (empty($article->faqs) && empty($updates['faqs'])) {
            $updates['faqs'] = [
                [
                    'question' => "{$keyword} là gì?",
                    'answer' => "Đây là chủ đề người dùng quan tâm khi tìm kiếm {$keyword}; bài viết cần giải thích rõ khái niệm, lợi ích và cách áp dụng.",
                ],
                [
                    'question' => "Nên bắt đầu {$keyword} như thế nào?",
                    'answer' => 'Nên bắt đầu từ mục tiêu, thông tin cần chuẩn bị, sau đó triển khai từng bước và đo lường kết quả.',
                ],
            ];
        }

        if (!empty($updates)) {
            $article->update($updates);
        }

        $this->qualityReportService->generate($article->fresh(['keyword', 'wordpressSite']));

        return $article->fresh(['keyword', 'wordpressSite', 'user']);
    }
}
