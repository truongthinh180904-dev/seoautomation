<?php

namespace App\Services\Quality;

use App\Models\Article;
use App\Models\Website;
use App\Services\Brand\BrandKnowledgeService;
use App\Services\Brand\IndustryProfileRegistry;

class TrustAndFactVerificationService
{
    public function __construct(
        protected BrandKnowledgeService $brandKnowledgeService,
        protected IndustryProfileRegistry $industryRegistry
    ) {}

    /**
     * Kiểm tra toàn diện Độ tin cậy, Phân tầng dữ liệu (Tier A/B/C), E-E-A-T và YMYL (Section 7).
     *
     * @param  array<string, mixed>  $strategyAnalysis
     * @return array<string, mixed>
     */
    public function verifyArticle(Article $article, array $strategyAnalysis = []): array
    {
        $website = $article->website ?: ($article->website_id ? Website::find($article->website_id) : null);
        $brandKnowledgeId = (int) (
            data_get($article->pipeline_status, 'content_brief.brand_knowledge_id')
            ?: data_get($article->outline, 'content_brief.brand_knowledge_id')
            ?: 0
        ) ?: null;

        $brandContext = $website
            ? $this->brandKnowledgeService->extractStructuredContext($website, $brandKnowledgeId)
            : $this->brandKnowledgeService->extractStructuredContext(0, null);

        $industryKey = (string) (
            data_get($strategyAnalysis, 'step_1_business_context.industry_key')
            ?: ($brandContext['industry_key'] ?? IndustryProfileRegistry::INDUSTRY_OTHER)
        );
        $industryProfile = $this->industryRegistry->getProfile($industryKey);
        $ymylLevel = (string) ($industryProfile['ymyl_level'] ?? 'low');

        $htmlContent = (string) $article->content;
        $plainText = trim(strip_tags($htmlContent));
        $lowerText = mb_strtolower($plainText, 'UTF-8');

        // 7.1 Phân loại dữ liệu Tier A, Tier B, Tier C
        $tierClassification = $this->classifyDataTiers($article, $brandContext, $strategyAnalysis);

        // 7.2 Rà soát Hallucination, tuyên bố nhạy cảm quá mức & Taboo Words
        $claimViolations = $this->detectProhibitedClaims($plainText, $lowerText, $brandContext, $industryProfile);
        $tabooCheck = $website
            ? $this->brandKnowledgeService->validateContentAgainstTaboo($plainText, $website->id, $brandKnowledgeId)
            : ['clean' => true, 'violations' => [], 'taboo_count' => 0];

        // 7.3 Đánh giá 4 trụ cột E-E-A-T
        $eeatEvaluation = $this->evaluateEeat($htmlContent, $plainText, $lowerText, $brandContext, $industryProfile);

        // 7.4 Kiểm tra YMYL & Disclaimer
        $ymylCheck = $this->evaluateYmylCompliance($lowerText, $industryProfile, $claimViolations);

        $criticalErrors = [];
        $warnings = [];

        foreach ($claimViolations as $violation) {
            if (($violation['severity'] ?? 'warning') === 'critical') {
                $criticalErrors[] = $violation['message'];
            } else {
                $warnings[] = $violation['message'];
            }
        }

        if (! $tabooCheck['clean']) {
            foreach ($tabooCheck['violations'] as $word => $count) {
                $criticalErrors[] = "Vi phạm từ ngữ cấm kỵ của thương hiệu: \"{$word}\" ({$count} lần).";
            }
        }

        if ($ymylCheck['is_ymyl'] && ! $ymylCheck['has_disclaimer']) {
            $warnings[] = 'Bài viết thuộc nhóm YMYL nhưng chưa có câu lưu ý tham khảo chuyên môn (Disclaimer) ở cuối bài.';
        }

        return [
            'industry_key' => $industryKey,
            'industry_name' => $industryProfile['name'] ?? 'Đa ngành',
            'ymyl_level' => $ymylLevel,
            'data_tiers' => $tierClassification,
            'claim_violations' => $claimViolations,
            'taboo_check' => $tabooCheck,
            'eeat_evaluation' => $eeatEvaluation,
            'ymyl_check' => $ymylCheck,
            'critical_errors' => $criticalErrors,
            'warnings' => $warnings,
            'is_clean' => count($criticalErrors) === 0,
        ];
    }

    /**
     * 7.1 Phân loại dữ liệu trong bài viết thành Hạng A, Hạng B, Hạng C.
     *
     * @param  array<string, mixed>  $brandContext
     * @param  array<string, mixed>  $strategyAnalysis
     * @return array<string, mixed>
     */
    protected function classifyDataTiers(Article $article, array $brandContext, array $strategyAnalysis): array
    {
        $plainText = strip_tags((string) $article->content);
        $tierAUsed = [];
        $tierAFacts = $brandContext['tier_a_verified_facts'] ?? [];

        if (! empty($tierAFacts['brand_name']) && mb_stripos($plainText, (string) $tierAFacts['brand_name'], 0, 'UTF-8') !== false) {
            $tierAUsed[] = 'Tên thương hiệu xác thực: '.$tierAFacts['brand_name'];
        }
        if (! empty($tierAFacts['service_area'])) {
            $tierAUsed[] = 'Khu vực phục vụ khai báo: '.$tierAFacts['service_area'];
        }
        if (! empty($tierAFacts['unique_selling_points']) && is_array($tierAFacts['unique_selling_points'])) {
            $tierAUsed[] = 'USP doanh nghiệp ('.count($tierAFacts['unique_selling_points']).' điểm)';
        }
        if (! empty($tierAFacts['expert_team']) && is_array($tierAFacts['expert_team'])) {
            $tierAUsed[] = 'Đội ngũ chuyên gia đã xác minh ('.count($tierAFacts['expert_team']).' nhân sự)';
        }
        if (! empty($tierAFacts['operating_license'])) {
            $tierAUsed[] = 'Giấy phép hoạt động đã xác minh: '.$tierAFacts['operating_license'];
        }

        $tierBSources = array_values(array_unique(array_merge(
            (array) ($brandContext['eeat_credentials']['reference_sources'] ?? []),
            (array) data_get($strategyAnalysis, 'step_4_content_strategy.reference_urls', [])
        )));

        return [
            'tier_a_verified_business_data' => [
                'description' => 'Thông tin do doanh nghiệp trực tiếp khai báo trong Business Profile',
                'verified_items_available' => array_keys($tierAFacts),
                'verified_items_used' => $tierAUsed,
                'unverified_fields_restricted' => $brandContext['unverified_fields'] ?? [],
            ],
            'tier_b_verified_external_info' => [
                'description' => 'Kiến thức ngành phổ quát được chấp nhận rộng rãi và nguồn tham khảo đáng tin cậy',
                'reference_sources' => $tierBSources,
            ],
            'tier_c_ai_suggestions' => [
                'description' => 'Ví dụ minh họa, cấu trúc phân tích, bảng so sánh tiêu chí tổng quát không chứa tuyên bố số liệu giả mạo',
            ],
        ];
    }

    /**
     * 7.2 Phát hiện các tuyên bố vi phạm nguyên tắc chống Hallucination hoặc phóng đại YMYL.
     *
     * @param  array<string, mixed>  $brandContext
     * @param  array<string, mixed>  $industryProfile
     * @return array<int, array{type: string, severity: string, matched: string, message: string}>
     */
    protected function detectProhibitedClaims(
        string $plainText,
        string $lowerText,
        array $brandContext,
        array $industryProfile
    ): array {
        $violations = [];

        // 1. Kiểm tra cam kết tuyệt đối 100% phi thực tế trong Y tế, Luật, Tài chính, BĐS
        $absolutePatterns = [
            '/cam kết\s+(?:chữa\s+)?khỏi\s+100%/iu' => 'Tuyên bố y khoa tuyệt đối ("cam kết khỏi 100%") vi phạm quy tắc an toàn YMYL.',
            '/chữa khỏi\s+(?:hoàn toàn|vĩnh viễn)\s+100%/iu' => 'Tuyên bố chữa khỏi vĩnh viễn 100% không có căn cứ y khoa.',
            '/không đau\s+100%|an toàn\s+tuyệt đối\s+100%/iu' => 'Tuyên bố tuyệt đối 100% không nêu điều kiện hoặc rủi ro y khoa.',
            '/đảm bảo\s+thắng kiện\s+100%|cam kết\s+thắng kiện/iu' => 'Tuyên bố cam kết thắng kiện 100% vi phạm quy tắc đạo đức và pháp lý.',
            '/cam kết\s+lợi nhuận\s+100%|chắc chắn\s+thắng\s+100%|lãi suất\s+cam kết\s+không rủi ro/iu' => 'Tuyên bố cam kết lợi nhuận/không rủi ro vi phạm quy tắc tài chính & đầu tư.',
        ];

        foreach ($absolutePatterns as $regex => $msg) {
            if (preg_match($regex, $plainText, $m)) {
                $violations[] = [
                    'type' => 'prohibited_absolute_claim',
                    'severity' => 'critical',
                    'matched' => $m[0],
                    'message' => $msg,
                ];
            }
        }

        // 2. Kiểm tra AI tự xưng là Bác sĩ / Luật sư / Chuyên gia ở ngôi thứ nhất
        if (preg_match('/(tôi là bác sĩ|tôi là luật sư|với tư cách là bác sĩ|qua \d+ năm trực tiếp điều trị của tôi)/iu', $plainText, $m)) {
            $violations[] = [
                'type' => 'false_first_person_persona',
                'severity' => 'critical',
                'matched' => $m[0],
                'message' => 'AI tự nhận mình là bác sĩ/luật sư hoặc người có trải nghiệm điều trị trực tiếp ở ngôi thứ nhất.',
            ];
        }

        // 3. Kiểm tra tự bịa số giấy phép cụ thể khi hồ sơ doanh nghiệp chưa khai báo operating_license
        $hasVerifiedLicense = ! empty($brandContext['tier_a_verified_facts']['operating_license']);
        if (! $hasVerifiedLicense && preg_match('/giấy phép\s+(?:hoạt động\s+)?số\s*[:#]?\s*[0-9]{3,}\/[A-Z\-]+/iu', $plainText, $m)) {
            $violations[] = [
                'type' => 'unverified_license_number',
                'severity' => 'critical',
                'matched' => $m[0],
                'message' => 'Bài viết chứa mã số giấy phép cụ thể chưa được khai báo trong Hồ sơ Doanh nghiệp (Tier A).',
            ];
        }

        return $violations;
    }

    /**
     * 7.3 Đánh giá E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness).
     *
     * @param  array<string, mixed>  $brandContext
     * @param  array<string, mixed>  $industryProfile
     * @return array<string, mixed>
     */
    protected function evaluateEeat(
        string $htmlContent,
        string $plainText,
        string $lowerText,
        array $brandContext,
        array $industryProfile
    ): array {
        // Experience: có ví dụ thực tế, tiêu chí đánh giá, quy trình từng bước hoặc bảng so sánh
        $hasPracticalElements = str_contains($htmlContent, '<table')
            || str_contains($htmlContent, '<ol')
            || preg_match('/(quy trình|bước 1|tiêu chí|kinh nghiệm|lưu ý thực tế|trường hợp)/iu', $plainText);

        // Expertise: sử dụng thuật ngữ chuyên ngành của Industry Profile
        $domainTerms = $industryProfile['domain_terminology'] ?? [];
        $matchedTerms = [];
        foreach ($domainTerms as $term) {
            if (mb_stripos($plainText, (string) $term, 0, 'UTF-8') !== false) {
                $matchedTerms[] = $term;
            }
        }

        // Authoritativeness: gắn kết với thương hiệu và nguồn tham chiếu
        $brandName = (string) ($brandContext['brand_name'] ?? '');
        $mentionsBrand = $brandName !== '' && mb_stripos($plainText, $brandName, 0, 'UTF-8') !== false;

        // Trustworthiness: có phân tích lưu ý/điều kiện áp dụng, minh bạch thông tin
        $hasBalancedCaveats = (bool) preg_match('/(lưu ý|tùy thuộc|điều kiện|tham khảo|khuyến nghị|chính sách)/iu', $plainText);

        $scoreOutOf15 = 0;
        if ($hasPracticalElements) {
            $scoreOutOf15 += 4;
        }
        if (count($matchedTerms) >= 2) {
            $scoreOutOf15 += 4;
        } elseif (count($matchedTerms) === 1) {
            $scoreOutOf15 += 2;
        }
        if ($mentionsBrand || ! empty($brandContext['has_profile'])) {
            $scoreOutOf15 += 3;
        }
        if ($hasBalancedCaveats) {
            $scoreOutOf15 += 4;
        }

        return [
            'experience_demonstrated' => (bool) $hasPracticalElements,
            'expertise_domain_terms_matched' => $matchedTerms,
            'authoritativeness_brand_aligned' => $mentionsBrand,
            'trustworthiness_balanced_caveats' => $hasBalancedCaveats,
            'eeat_score_15' => min(15, $scoreOutOf15),
        ];
    }

    /**
     * 7.4 Đánh giá mức độ tuân thủ YMYL.
     *
     * @param  array<string, mixed>  $industryProfile
     * @param  array<int, array<string, mixed>>  $claimViolations
     * @return array<string, mixed>
     */
    protected function evaluateYmylCompliance(string $lowerText, array $industryProfile, array $claimViolations): array
    {
        $ymylLevel = (string) ($industryProfile['ymyl_level'] ?? 'low');
        $isYmyl = in_array($ymylLevel, ['high', 'medium'], true);
        $requiredDisclaimer = $industryProfile['required_disclaimer'] ?? null;

        $hasDisclaimer = true;
        if ($ymylLevel === 'high' && ! empty($requiredDisclaimer)) {
            $hasDisclaimer = (bool) preg_match('/(lưu ý|miễn trừ|tham khảo|không thay thế|chuyên gia|bác sĩ|luật sư)/iu', $lowerText);
        }

        return [
            'is_ymyl' => $isYmyl,
            'ymyl_level' => $ymylLevel,
            'verification_requirement' => $industryProfile['verification_requirement'] ?? 'standard',
            'has_disclaimer' => $hasDisclaimer,
            'required_disclaimer_text' => $requiredDisclaimer,
            'passed_ymyl_safety' => count($claimViolations) === 0,
        ];
    }
}
