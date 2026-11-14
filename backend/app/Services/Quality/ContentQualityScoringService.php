<?php

namespace App\Services\Quality;

use App\Models\Article;
use App\Models\Website;
use App\Services\Article\ArticleContentFormatter;
use App\Services\Article\ContentStrategyService;
use App\Services\Brand\BrandKnowledgeService;
use App\Services\Media\ImagePlannerService;
use App\Services\SEO\SchemaBuilderService;
use Illuminate\Support\Str;

class ContentQualityScoringService
{
    public function __construct(
        protected BrandKnowledgeService $brandKnowledgeService,
        protected ContentStrategyService $strategyService,
        protected TrustAndFactVerificationService $factVerificationService,
        protected ContentReviewPipelineService $reviewPipelineService,
        protected SchemaBuilderService $schemaBuilderService,
        protected ImagePlannerService $imagePlannerService,
        protected ArticleContentFormatter $formatter
    ) {}

    /**
     * Chấm điểm bài viết theo thang 100 điểm 7 trụ cột, kiểm tra Critical Error Gate (Section 11)
     * và đóng gói đầy đủ 19 trường Output chuẩn (Section 12).
     *
     * @return array<string, mixed>
     */
    public function evaluateAndPackage(Article $article, bool $runAutoFix = true): array
    {
        $website = $article->website ?: ($article->website_id ? Website::find($article->website_id) : null);
        $keywordModel = $article->keyword;
        $brief = (array) (data_get($article->pipeline_status, 'content_brief')
            ?? data_get($article->outline, 'content_brief')
            ?? []);

        $strategyAnalysis = (array) (data_get($article->outline, 'strategy_analysis') ?? []);
        if (empty($strategyAnalysis) && $website && $keywordModel) {
            $strategyAnalysis = $this->strategyService->analyzeBeforeWriting($website, $keywordModel, $brief);
        }

        // 1. Chạy Pipeline Kiểm duyệt 4 vai trò & Targeted Auto-Fix (Section 10)
        $reviewReport = $runAutoFix
            ? $this->reviewPipelineService->reviewAndAutoFix($article, $strategyAnalysis)
            : [
                'fact_verification_detail' => $this->factVerificationService->verifyArticle($article, $strategyAnalysis),
                'targeted_fixes_applied' => [],
                'overall_passed' => true,
            ];

        $article->refresh();
        $factDetail = $reviewReport['fact_verification_detail'] ?? $this->factVerificationService->verifyArticle($article, $strategyAnalysis);

        // 2. Chấm điểm 7 trụ cột = 100 điểm (Section 11)
        $scoring = $this->calculate100PointRubric($article, $strategyAnalysis, $factDetail);

        // 3. Sinh Schema JSON-LD hợp lệ theo dữ liệu thực trong bài (Section 9.4)
        $schemaResult = $this->schemaBuilderService->buildForArticle($article, $strategyAnalysis);

        // 4. Đảm bảo Image Plan + Prompts + ALT + Caption đã sẵn sàng (Section 8)
        $imagePlan = data_get($article->media_plan, 'image_plan');
        if (empty($imagePlan)) {
            $imagePlan = $this->imagePlannerService->buildAndAttachPlan($article);
        }

        // 5. Đóng gói 19 trường Output (Section 12)
        $outputPackage = $this->build19FieldOutputPackage(
            article: $article,
            strategyAnalysis: $strategyAnalysis,
            factDetail: $factDetail,
            reviewReport: $reviewReport,
            scoring: $scoring,
            schemaResult: $schemaResult,
            imagePlan: $imagePlan
        );

        return [
            'scoring' => $scoring,
            'review_pipeline' => $reviewReport,
            'schema_markup' => $schemaResult,
            'image_plan' => $imagePlan,
            'output_package_19_fields' => $outputPackage,
        ];
    }

    /**
     * Tính toán điểm chất lượng 100 điểm theo đúng 7 tiêu chí của Section 11 & kiểm tra Critical Error Gate.
     *
     * @param  array<string, mixed>  $strategyAnalysis
     * @param  array<string, mixed>  $factDetail
     * @return array<string, mixed>
     */
    public function calculate100PointRubric(Article $article, array $strategyAnalysis = [], array $factDetail = []): array
    {
        $html = (string) $article->content;
        $plain = trim(strip_tags($html));
        $wordCount = (int) ($article->word_count ?: $this->formatter->wordCount($html));
        $keyword = trim((string) ($article->focus_keyword ?: $article->keyword?->keyword ?: ''));

        // Trụ cột 1: Search Intent & Audience Fit (15 điểm)
        $intentScore = 0;
        if ($wordCount >= 600) {
            $intentScore += 5;
        }
        if ($keyword === '' || mb_stripos($plain, $keyword, 0, 'UTF-8') !== false) {
            $intentScore += 5;
        }
        $hasDirectOpening = ! preg_match('/^(trong thời đại|như chúng ta đã biết)/iu', mb_substr($plain, 0, 120));
        if ($hasDirectOpening && mb_strlen($plain, 'UTF-8') > 200) {
            $intentScore += 5;
        }

        // Trụ cột 2: Content Depth & Helpfulness (20 điểm)
        $depthScore = 0;
        $targetMinWords = (int) (data_get($strategyAnalysis, 'step_4_content_strategy.target_word_count_range.0') ?: 800);
        if ($wordCount >= $targetMinWords) {
            $depthScore += 8;
        } elseif ($wordCount >= 600) {
            $depthScore += 5;
        }
        $h2Count = preg_match_all('/<h2\b[^>]*>/i', $html);
        if ($h2Count >= 4) {
            $depthScore += 6;
        } elseif ($h2Count >= 3) {
            $depthScore += 4;
        }
        $hasTableOrSteps = str_contains($html, '<table') || str_contains($html, '<ol') || str_contains($html, '<ul');
        if ($hasTableOrSteps) {
            $depthScore += 4;
        }
        if (! empty($article->faqs) || preg_match('/(câu hỏi thường gặp|faq|hỏi\s*:)/iu', $plain)) {
            $depthScore += 2;
        }

        // Trụ cột 3: SEO On-page (15 điểm)
        $seoScore = 0;
        $hasKwInTitle = $keyword === '' || mb_stripos((string) $article->title, $keyword, 0, 'UTF-8') !== false;
        if ($hasKwInTitle) {
            $seoScore += 4;
        }
        if (! empty($article->seo_title) && mb_strlen((string) $article->seo_title, 'UTF-8') <= 70) {
            $seoScore += 3;
        }
        if (! empty($article->seo_description) && mb_strlen((string) $article->seo_description, 'UTF-8') >= 70) {
            $seoScore += 3;
        }
        if (! empty($article->slug)) {
            $seoScore += 2;
        }
        $density = $this->calculateKeywordDensity($plain, $keyword, $wordCount);
        $isStuffed = $density > 3.5;
        if (! $isStuffed && $density > 0) {
            $seoScore += 3;
        }

        // Trụ cột 4: E-E-A-T & Trustworthiness (15 điểm)
        $eeatScore = (int) ($factDetail['eeat_evaluation']['eeat_score_15'] ?? 12);

        // Trụ cột 5: Factual Accuracy & Industry Compliance (20 điểm)
        $factualScore = 20;
        $claimViolationsCount = count($factDetail['claim_violations'] ?? []);
        $tabooCount = (int) ($factDetail['taboo_check']['taboo_count'] ?? 0);
        if ($claimViolationsCount > 0) {
            $factualScore -= min(15, $claimViolationsCount * 8);
        }
        if ($tabooCount > 0) {
            $factualScore -= min(10, $tabooCount * 5);
        }
        if (! empty($factDetail['ymyl_check']['is_ymyl']) && empty($factDetail['ymyl_check']['has_disclaimer'])) {
            $factualScore -= 4;
        }
        $factualScore = max(0, $factualScore);

        // Trụ cột 6: Readability & User Experience (10 điểm)
        $readabilityScore = 0;
        $pCount = preg_match_all('/<p\b[^>]*>/i', $html);
        if ($pCount >= 5) {
            $readabilityScore += 4;
        }
        if ($hasTableOrSteps) {
            $readabilityScore += 3;
        }
        if (! preg_match('/lorem ipsum|placeholder|TODO|\{\{/i', $html)) {
            $readabilityScore += 3;
        }

        // Trụ cột 7: Conversion Optimization (5 điểm)
        $conversionScore = 0;
        $hasCta = ! empty($article->primary_cta)
            || (bool) preg_match('/(liên hệ|tư vấn|đăng ký|đặt lịch|báo giá|dùng thử)/iu', $plain);
        if ($hasCta) {
            $conversionScore = 5;
        }

        $totalScore = min(100, $intentScore + $depthScore + $seoScore + $eeatScore + $factualScore + $readabilityScore + $conversionScore);

        // 11.2 Critical Error Gate
        $criticalErrors = $factDetail['critical_errors'] ?? [];
        if ($isStuffed) {
            $criticalErrors[] = sprintf('Nhồi nhét từ khóa (Keyword Stuffing): mật độ %.2f%% vượt ngưỡng an toàn 3.5%%.', $density);
        }
        if ($wordCount < 300 || trim($plain) === '') {
            $criticalErrors[] = 'Nội dung bài viết quá ngắn hoặc rỗng.';
        }

        if (! empty($criticalErrors)) {
            $gateStatus = 'BLOCK';
            $gateLabel = 'Không đạt (Vi phạm Critical Error Gate — cần xử lý lỗi nghiêm trọng)';
        } elseif ($totalScore >= 85) {
            $gateStatus = 'PASS';
            $gateLabel = 'Đạt tiêu chuẩn xuất bản (>= 85 điểm)';
        } elseif ($totalScore >= 75) {
            $gateStatus = 'REVIEW';
            $gateLabel = 'Cần tự động tối ưu thêm hoặc rà soát (75–84 điểm)';
        } else {
            $gateStatus = 'BLOCK';
            $gateLabel = 'Chưa đạt ngưỡng xuất bản (< 75 điểm)';
        }

        return [
            'total_score' => $totalScore,
            'gate_status' => $gateStatus,
            'gate_label' => $gateLabel,
            'critical_error_gate_triggered' => ! empty($criticalErrors),
            'critical_errors' => $criticalErrors,
            'keyword_density_percent' => round($density, 2),
            'pillars' => [
                'search_intent_and_audience_fit' => ['score' => $intentScore, 'max' => 15],
                'content_depth_and_helpfulness' => ['score' => $depthScore, 'max' => 20],
                'seo_on_page' => ['score' => $seoScore, 'max' => 15],
                'eeat_and_trustworthiness' => ['score' => $eeatScore, 'max' => 15],
                'factual_accuracy_and_industry_compliance' => ['score' => $factualScore, 'max' => 20],
                'readability_and_user_experience' => ['score' => $readabilityScore, 'max' => 10],
                'conversion_optimization' => ['score' => $conversionScore, 'max' => 5],
            ],
        ];
    }

    /**
     * Xây dựng gói kết quả trả về đầy đủ 19 trường theo đúng chuẩn PHẦN X (Section 12).
     *
     * @param  array<string, mixed>  $strategyAnalysis
     * @param  array<string, mixed>  $factDetail
     * @param  array<string, mixed>  $reviewReport
     * @param  array<string, mixed>  $scoring
     * @param  array<string, mixed>  $schemaResult
     * @param  array<string, mixed>  $imagePlan
     * @return array<string, mixed>
     */
    protected function build19FieldOutputPackage(
        Article $article,
        array $strategyAnalysis,
        array $factDetail,
        array $reviewReport,
        array $scoring,
        array $schemaResult,
        array $imagePlan
    ): array {
        $s1 = $strategyAnalysis['step_1_business_context'] ?? [];
        $s2 = $strategyAnalysis['step_2_keyword_analysis'] ?? [];
        $s3 = $strategyAnalysis['step_3_audience_analysis'] ?? [];
        $s4 = $strategyAnalysis['step_4_content_strategy'] ?? [];

        $outlineData = is_array($article->outline) ? $article->outline : [];
        unset($outlineData['strategy_analysis']);

        $unverifiedFields = $s1['unverified_information_do_not_fabricate']
            ?? ($factDetail['data_tiers']['tier_a_verified_business_data']['unverified_fields_restricted'] ?? []);

        $verificationNotes = [];
        if (! empty($unverifiedFields)) {
            $verificationNotes[] = 'Các trường thông tin doanh nghiệp chưa khai báo (hệ thống đã tự động khóa suy diễn để tránh bịa đặt): '.implode(', ', $unverifiedFields).'.';
        }
        foreach (($factDetail['warnings'] ?? []) as $w) {
            $verificationNotes[] = $w;
        }
        foreach (($reviewReport['targeted_fixes_applied'] ?? []) as $fix) {
            $verificationNotes[] = '[Auto-Fix] '.$fix;
        }

        return [
            '1_business_summary' => [
                'brand_name' => $s1['brand_name'] ?? '',
                'business_model' => $s1['business_model'] ?? 'B2C',
                'market_and_area' => $s1['market_and_area'] ?? 'Việt Nam',
                'brand_tone' => $s1['brand_tone'] ?? 'professional',
                'unique_selling_points' => $s1['unique_selling_points'] ?? [],
                'usable_verified_facts_keys' => array_keys($s1['usable_verified_information'] ?? []),
            ],
            '2_industry_profile' => [
                'industry_key' => $factDetail['industry_key'] ?? ($s1['industry_key'] ?? 'other'),
                'industry_name' => $factDetail['industry_name'] ?? ($s1['industry_name'] ?? 'Đa ngành'),
                'ymyl_level' => $factDetail['ymyl_level'] ?? ($s1['ymyl_level'] ?? 'low'),
                'verification_requirement' => $s1['verification_requirement'] ?? 'standard',
            ],
            '3_content_type' => [
                'key' => $s4['content_type'] ?? ($outlineData['content_type'] ?? 'informational_article'),
                'label' => $s4['content_type_label'] ?? 'Informational Article',
                'length_tier' => $s4['length_tier'] ?? 'standard',
            ],
            '4_search_intent' => [
                'primary_intent' => $s2['primary_search_intent'] ?? ($outlineData['primary_search_intent'] ?? 'informational'),
                'secondary_intents' => $s2['secondary_search_intents'] ?? ($outlineData['secondary_search_intents'] ?? []),
            ],
            '5_keyword_map' => [
                'primary_keyword' => $s2['primary_keyword'] ?? ($article->focus_keyword ?: ''),
                'secondary_keywords' => $s2['secondary_keywords'] ?? [],
                'semantic_keywords' => $s2['semantic_keywords'] ?? [],
                'long_tail_keywords' => $s2['long_tail_keywords'] ?? [],
                'related_entities' => $s2['related_entities'] ?? [],
            ],
            '6_content_strategy' => [
                'article_goal' => $s4['article_goal'] ?? '',
                'differentiated_angle' => $s4['differentiated_angle'] ?? '',
                'core_message' => $s4['core_message'] ?? '',
                'audience_analysis' => $s3,
            ],
            '7_article_outline' => $outlineData,
            '8_seo_title' => (string) ($article->seo_title ?: Str::limit((string) $article->title, 65, '')),
            '9_meta_description' => (string) ($article->seo_description ?: $article->excerpt ?: ''),
            '10_url_slug' => (string) ($article->slug ?: Str::slug((string) $article->focus_keyword)),
            '11_h1' => (string) ($article->title ?: $article->focus_keyword),
            '12_full_article_html' => (string) $article->content,
            '13_faq_section' => $article->faqs ?? ($outlineData['faqs'] ?? []),
            '14_cta_section' => (string) ($article->primary_cta ?: ($s4['recommended_cta'] ?? 'Liên hệ tư vấn chi tiết')),
            '15_internal_link_suggestions' => ! empty($article->internal_links)
                ? $article->internal_links
                : ($s4['internal_link_opportunities'] ?? []),
            '16_image_plan_and_prompts' => $imagePlan,
            '17_schema_json_ld' => $schemaResult,
            '18_content_quality_score' => $scoring,
            '19_warnings_and_verification_notes' => $verificationNotes,
        ];
    }

    protected function calculateKeywordDensity(string $plainText, string $keyword, int $wordCount): float
    {
        if ($keyword === '' || $wordCount <= 0) {
            return 1.0;
        }

        $kwWords = max(1, count(preg_split('/\s+/u', trim($keyword)) ?: [1]));
        $occurrences = mb_substr_count(mb_strtolower($plainText, 'UTF-8'), mb_strtolower($keyword, 'UTF-8'), 'UTF-8');

        return (($occurrences * $kwWords) / max(1, $wordCount)) * 100;
    }
}
