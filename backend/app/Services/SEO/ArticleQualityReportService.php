<?php

namespace App\Services\SEO;

use App\Models\Article;
use App\Models\QualityReport;

class ArticleQualityReportService
{
    public function generate(Article $article): QualityReport
    {
        $article->loadMissing(['keyword', 'wordpressSite']);

        $checks = [];
        $warnings = [];
        $blockingErrors = [];
        $autoFixable = [];

        $keyword = mb_strtolower($article->keyword->keyword ?? $article->focus_keyword ?? '');
        $title = mb_strtolower($article->title ?? '');
        $contentText = mb_strtolower(strip_tags((string) $article->content));
        $wordCount = $article->word_count ?: str_word_count($contentText);
        $metaTitleLength = mb_strlen((string) $article->seo_title);
        $metaDescLength = mb_strlen((string) $article->seo_description);
        $headingCount = preg_match_all('/<h[12][^>]*>/i', (string) $article->content);

        $seoScore = 0;
        $seoScore += $this->check($checks, 'keyword_in_title', $keyword !== '' && str_contains($title, $keyword), 10, 'Keyword xuất hiện trong title');
        $seoScore += $this->check($checks, 'keyword_in_first_paragraph', $keyword !== '' && str_contains(mb_substr($contentText, 0, 500), $keyword), 5, 'Keyword xuất hiện sớm trong nội dung');
        $seoScore += $this->check($checks, 'meta_title_length_ok', $metaTitleLength >= config('quality.meta_title_min') && $metaTitleLength <= config('quality.meta_title_max'), 10, 'Meta title đúng độ dài');
        $seoScore += $this->check($checks, 'meta_desc_length_ok', $metaDescLength >= config('quality.meta_desc_min') && $metaDescLength <= config('quality.meta_desc_max'), 10, 'Meta description đúng độ dài');
        $seoScore += $this->check($checks, 'internal_links_count_ok', count((array) $article->internal_links) >= config('quality.min_internal_links'), 5, 'Đủ internal links');

        $readabilityScore = 0;
        $readabilityScore += $this->check($checks, 'word_count_ok', $wordCount >= config('quality.min_word_count'), 10, 'Đạt số từ tối thiểu');
        $readabilityScore += $this->check($checks, 'heading_structure_ok', $headingCount >= config('quality.min_heading_count'), 10, 'Đủ heading chính');
        $readabilityScore += $this->check($checks, 'faq_exists', !config('quality.faq_required') || !empty($article->faqs), 5, 'FAQ đạt yêu cầu');
        $readabilityScore += $this->check($checks, 'no_placeholder_text', !preg_match('/lorem ipsum|placeholder|TODO/i', (string) $article->content), 5, 'Không có placeholder text');

        $mediaScore = 0;
        $mediaScore += $this->check($checks, 'featured_image_exists', filled($article->featured_image_url) || filled($article->image_assets) || !config('quality.featured_image_required'), 10, 'Featured image đạt yêu cầu');
        $mediaScore += $this->check($checks, 'images_have_alt', true, 5, 'Alt ảnh sẵn sàng kiểm tra chi tiết ở media pipeline');

        $wpScore = 0;
        $wpScore += $this->check($checks, 'slug_exists', filled($article->wp_slug) || filled($article->slug), 5, 'Có slug WordPress');
        $wpScore += $this->check($checks, 'wordpress_site_exists', filled($article->wordpress_site_id), 5, 'Có WordPress site');
        $wpScore += $this->check($checks, 'content_not_empty', trim($contentText) !== '', 5, 'Content không rỗng');

        foreach ($checks as $check) {
            if (!$check['passed']) {
                $warnings[] = $check['message'];
                if (in_array($check['key'], ['meta_title_length_ok', 'meta_desc_length_ok', 'internal_links_count_ok', 'featured_image_exists'], true)) {
                    $autoFixable[] = $check['key'];
                }
            }
        }

        if ($wordCount < 300 || trim($contentText) === '') {
            $blockingErrors[] = 'Bài viết quá ngắn hoặc rỗng, không nên publish.';
        }

        $report = QualityReport::create([
            'article_id' => $article->id,
            'seo_score' => min(40, $seoScore),
            'readability_score' => min(30, $readabilityScore),
            'media_score' => min(15, $mediaScore),
            'wordpress_readiness_score' => min(15, $wpScore),
            'total_score' => min(100, $seoScore + $readabilityScore + $mediaScore + $wpScore),
            'checks' => $checks,
            'warnings' => $warnings,
            'blocking_errors' => $blockingErrors,
            'auto_fixable' => $autoFixable,
            'generated_at' => now(),
        ]);

        $article->update([
            'quality_report' => $report->toArray(),
            'seo_score' => $report->total_score,
        ]);

        return $report;
    }

    protected function check(array &$checks, string $key, bool $passed, int $points, string $message): int
    {
        $checks[] = compact('key', 'passed', 'points', 'message');

        return $passed ? $points : 0;
    }
}
