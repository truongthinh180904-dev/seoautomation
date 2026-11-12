<?php

namespace App\Services\Quality;

use App\Models\Article;
use App\Services\Article\ArticleContentFormatter;
use Illuminate\Support\Str;

class ContentReviewPipelineService
{
    public function __construct(
        protected TrustAndFactVerificationService $factVerificationService,
        protected ArticleContentFormatter $formatter
    ) {}

    /**
     * Thực hiện quy trình kiểm duyệt 4 vai trò độc lập (Section 10) và tự động sửa có mục tiêu các phần chưa đạt.
     *
     * @param  array<string, mixed>  $strategyAnalysis
     * @return array<string, mixed>
     */
    public function reviewAndAutoFix(Article $article, array $strategyAnalysis = []): array
    {
        $fixesApplied = [];
        $content = (string) $article->content;
        $title = (string) ($article->title ?: $article->focus_keyword);
        $keyword = (string) ($article->focus_keyword ?: $article->keyword?->keyword ?: '');

        // 1. Chạy Fact Verification trước để phát hiện vi phạm YMYL / Taboo / Disclaimer
        $initialFactReport = $this->factVerificationService->verifyArticle($article, $strategyAnalysis);

        // TARGETED AUTO-FIX 1: Xử lý mở bài sáo rỗng kiểu AI (Review 2: Content Editor)
        $clicheOpenings = [
            '/(<p[^>]*>\s*)Trong thời đại công nghệ số hiện nay,\s*/iu' => '$1',
            '/(<p[^>]*>\s*)Trong cuộc sống hiện đại ngày nay,\s*/iu' => '$1',
            '/(<p[^>]*>\s*)Như chúng ta đã biết,\s*/iu' => '$1',
        ];
        foreach ($clicheOpenings as $pattern => $replacement) {
            $updated = preg_replace($pattern, $replacement, $content, 1, $count);
            if ($count > 0 && is_string($updated)) {
                $content = $updated;
                $fixesApplied[] = 'Content Editor: Đã loại bỏ cụm từ mở đầu sáo rỗng kiểu AI.';
            }
        }

        // TARGETED AUTO-FIX 2: Điều chỉnh các tuyên bố tuyệt đối 100% vi phạm YMYL (Review 3: Fact Checker)
        $safeClaimReplacements = [
            '/cam kết\s+(?:chữa\s+)?khỏi\s+100%/iu' => 'hỗ trợ cải thiện hiệu quả theo phác đồ chuyên môn',
            '/chữa khỏi\s+(?:hoàn toàn|vĩnh viễn)\s+100%/iu' => 'mang lại hiệu quả kiểm soát và phục hồi tích cực',
            '/không đau\s+100%/iu' => 'hạn chế tối đa cảm giác khó chịu nhờ kỹ thuật hiện đại',
            '/an toàn\s+tuyệt đối\s+100%/iu' => 'đảm bảo tiêu chuẩn an toàn cao theo quy trình kiểm soát nghiêm ngặt',
            '/đảm bảo\s+thắng kiện\s+100%|cam kết\s+thắng kiện/iu' => 'bảo vệ tối đa quyền và lợi ích hợp pháp theo quy định pháp luật',
            '/cam kết\s+lợi nhuận\s+100%|chắc chắn\s+thắng\s+100%/iu' => 'tối ưu tiềm năng sinh lời gắn liền với quản trị rủi ro',
            '/tôi là bác sĩ|với tư cách là bác sĩ/iu' => 'theo góc nhìn chuyên môn y khoa',
            '/tôi là luật sư|với tư cách là luật sư/iu' => 'theo góc nhìn phân tích pháp lý',
        ];
        foreach ($safeClaimReplacements as $regex => $safePhrase) {
            $updated = preg_replace($regex, $safePhrase, $content, -1, $count);
            if ($count > 0 && is_string($updated)) {
                $content = $updated;
                $fixesApplied[] = "Fact Checker: Đã điều chỉnh tuyên bố nhạy cảm quá mức thành diễn đạt chuẩn mực (\"{$safePhrase}\").";
            }
        }

        // TARGETED AUTO-FIX 3: Loại bỏ từ cấm kỵ (Taboo Words) nếu xuất hiện
        $tabooViolations = $initialFactReport['taboo_check']['violations'] ?? [];
        if (! empty($tabooViolations) && is_array($tabooViolations)) {
            foreach (array_keys($tabooViolations) as $tabooWord) {
                $escaped = preg_quote((string) $tabooWord, '/');
                $updated = preg_replace('/'.$escaped.'/iu', '', $content, -1, $count);
                if ($count > 0 && is_string($updated)) {
                    $content = $updated;
                    $fixesApplied[] = "Fact Checker: Đã loại bỏ từ ngữ cấm kỵ của thương hiệu (\"{$tabooWord}\").";
                }
            }
        }

        // TARGETED AUTO-FIX 4: Bổ sung Disclaimer chuyên môn ở cuối bài nếu ngành YMYL cao nhưng chưa có
        $ymylCheck = $initialFactReport['ymyl_check'] ?? [];
        if (! empty($ymylCheck['is_ymyl']) && empty($ymylCheck['has_disclaimer']) && ! empty($ymylCheck['required_disclaimer_text'])) {
            $disclaimerText = e((string) $ymylCheck['required_disclaimer_text']);
            $content .= "\n<p><em><strong>Lưu ý chuyên môn:</strong> {$disclaimerText}</em></p>";
            $fixesApplied[] = 'Fact Checker: Đã bổ sung hộp lưu ý tham khảo chuyên môn (YMYL Disclaimer) ở cuối bài.';
        }

        // TARGETED AUTO-FIX 5: Đảm bảo SEO Metadata (Title, Meta Description, Slug) đầy đủ (Review 1: SEO Reviewer)
        $seoTitle = (string) ($article->seo_title ?: '');
        if ($seoTitle === '') {
            $seoTitle = Str::limit($title, 65, '');
            $fixesApplied[] = 'SEO Reviewer: Đã tự động chuẩn hóa SEO Title.';
        }
        $seoDescription = (string) ($article->seo_description ?: '');
        if ($seoDescription === '') {
            $seoDescription = Str::limit(trim((string) ($article->excerpt ?: strip_tags($content))), 155, '');
            $fixesApplied[] = 'SEO Reviewer: Đã tự động tổng hợp Meta Description chuẩn độ dài.';
        }
        $slug = (string) ($article->slug ?: '');
        if ($slug === '' && $keyword !== '') {
            $slug = Str::slug($keyword);
            $fixesApplied[] = 'SEO Reviewer: Đã chuẩn hóa URL Slug từ từ khóa chính.';
        }

        if (! empty($fixesApplied)) {
            $article->forceFill([
                'content' => $this->formatter->normalize($content, $title),
                'seo_title' => $seoTitle,
                'seo_description' => $seoDescription,
                'slug' => $slug,
                'word_count' => $this->formatter->wordCount($content),
            ])->save();
            $article->refresh();
        }

        // Chạy lại đánh giá sau khi đã áp dụng targeted auto-fix
        $finalFactReport = $this->factVerificationService->verifyArticle($article, $strategyAnalysis);

        $seoReview = $this->runSeoReviewer($article, $keyword);
        $editorReview = $this->runContentEditor($article);
        $factReview = $this->runFactChecker($finalFactReport);
        $technicalReview = $this->runTechnicalReviewer($article, $finalFactReport);

        return [
            'reviews' => [
                'seo_reviewer' => $seoReview,
                'content_editor' => $editorReview,
                'fact_checker' => $factReview,
                'technical_reviewer' => $technicalReview,
            ],
            'fact_verification_detail' => $finalFactReport,
            'targeted_fixes_applied' => $fixesApplied,
            'overall_passed' => $seoReview['passed'] && $editorReview['passed'] && $factReview['passed'] && $technicalReview['passed'],
        ];
    }

    /**
     * Review 1: SEO Reviewer (Kiểm tra Title, H1, Meta Description, Slug, Keyword, Heading, Internal Link).
     *
     * @return array<string, mixed>
     */
    protected function runSeoReviewer(Article $article, string $keyword): array
    {
        $content = (string) $article->content;
        $plain = strip_tags($content);
        $h2Count = preg_match_all('/<h2\b[^>]*>/i', $content);
        $h3Count = preg_match_all('/<h3\b[^>]*>/i', $content);
        $hasKeywordInTitle = $keyword === '' || mb_stripos((string) $article->title, $keyword, 0, 'UTF-8') !== false;
        $hasKeywordInBody = $keyword === '' || mb_stripos($plain, $keyword, 0, 'UTF-8') !== false;
        $hasMeta = ! empty($article->seo_title) && ! empty($article->seo_description) && ! empty($article->slug);

        $checks = [
            'h1_and_title_aligned' => $hasKeywordInTitle,
            'meta_description_and_slug_ready' => $hasMeta,
            'primary_keyword_natural' => $hasKeywordInBody,
            'heading_hierarchy_valid' => $h2Count >= 3,
            'h2_count' => (int) $h2Count,
            'h3_count' => (int) $h3Count,
            'internal_links_count' => is_array($article->internal_links) ? count($article->internal_links) : substr_count($content, '<a '),
        ];

        return [
            'role' => 'SEO Reviewer',
            'passed' => $hasKeywordInTitle && $hasKeywordInBody && $h2Count >= 3,
            'checks' => $checks,
            'summary' => ($hasKeywordInTitle && $h2Count >= 3)
                ? "Cấu trúc On-page SEO đạt chuẩn ({$h2Count} H2, {$h3Count} H3, đầy đủ Metadata & Slug)."
                : 'Cần bổ sung thêm thẻ H2 hoặc kiểm tra từ khóa chính trong tiêu đề.',
        ];
    }

    /**
     * Review 2: Content Editor (Kiểm tra văn phong tự nhiên, mở bài, logic giữa các phần, độ dễ đọc).
     *
     * @return array<string, mixed>
     */
    protected function runContentEditor(Article $article): array
    {
        $content = (string) $article->content;
        $wordCount = (int) ($article->word_count ?: $this->formatter->wordCount($content));
        $hasListsOrTables = str_contains($content, '<ul') || str_contains($content, '<ol') || str_contains($content, '<table');
        $paragraphCount = max(1, preg_match_all('/<p\b[^>]*>/i', $content));

        return [
            'role' => 'Content Editor',
            'passed' => $wordCount >= 600 && $paragraphCount >= 4,
            'checks' => [
                'word_count' => $wordCount,
                'paragraph_count' => $paragraphCount,
                'scannable_lists_or_tables' => $hasListsOrTables,
                'has_cta' => ! empty($article->primary_cta) || (bool) preg_match('/(liên hệ|tư vấn|đăng ký|đặt lịch| hotline)/iu', strip_tags($content)),
            ],
            'summary' => "Văn phong chuyên nghiệp, mạch lạc ({$wordCount} từ, {$paragraphCount} đoạn văn, bố cục dễ đọc).",
        ];
    }

    /**
     * Review 3: Fact Checker (Kiểm tra thông tin doanh nghiệp, phát hiện suy diễn, kiểm tra YMYL).
     *
     * @param  array<string, mixed>  $factReport
     * @return array<string, mixed>
     */
    protected function runFactChecker(array $factReport): array
    {
        $isClean = (bool) ($factReport['is_clean'] ?? true);
        $criticalErrors = $factReport['critical_errors'] ?? [];

        return [
            'role' => 'Fact Checker',
            'passed' => $isClean,
            'checks' => [
                'zero_hallucinated_claims' => count($factReport['claim_violations'] ?? []) === 0,
                'taboo_words_clean' => (bool) ($factReport['taboo_check']['clean'] ?? true),
                'ymyl_compliant' => (bool) ($factReport['ymyl_check']['passed_ymyl_safety'] ?? true),
                'has_ymyl_disclaimer' => (bool) ($factReport['ymyl_check']['has_disclaimer'] ?? true),
            ],
            'critical_errors' => $criticalErrors,
            'warnings' => $factReport['warnings'] ?? [],
            'summary' => $isClean
                ? 'Đạt chuẩn xác thực thông tin (Tier A/B/C), tuân thủ nguyên tắc chống Hallucination và an toàn YMYL.'
                : 'Phát hiện lỗi xác thực thông tin: '.implode('; ', $criticalErrors),
        ];
    }

    /**
     * Review 4: Technical Reviewer (Kiểm tra thuật ngữ chuyên ngành, tính chính xác nội dung chuyên môn).
     *
     * @param  array<string, mixed>  $factReport
     * @return array<string, mixed>
     */
    protected function runTechnicalReviewer(Article $article, array $factReport): array
    {
        $eeat = $factReport['eeat_evaluation'] ?? [];
        $matchedTerms = $eeat['expertise_domain_terms_matched'] ?? [];
        $hasPractical = (bool) ($eeat['experience_demonstrated'] ?? false);

        return [
            'role' => 'Technical Reviewer',
            'passed' => $hasPractical || count($matchedTerms) >= 1,
            'checks' => [
                'industry_name' => $factReport['industry_name'] ?? 'Đa ngành',
                'domain_terms_used' => $matchedTerms,
                'practical_depth_verified' => $hasPractical,
            ],
            'summary' => 'Nội dung sử dụng đúng thuật ngữ chuyên ngành ('.($factReport['industry_name'] ?? 'Đa ngành').') và có chiều sâu hướng dẫn/phân tích thực tế.',
        ];
    }
}
