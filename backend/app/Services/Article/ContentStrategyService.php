<?php

namespace App\Services\Article;

use App\Models\Article;
use App\Models\Keyword;
use App\Models\Website;
use App\Services\Brand\BrandKnowledgeService;
use App\Services\Brand\IndustryProfileRegistry;

class ContentStrategyService
{
    public const CONTENT_TYPES = [
        'pillar_page' => [
            'key' => 'pillar_page',
            'label' => 'Pillar Page (Trang trụ cột chuyên sâu)',
            'default_length' => 'advanced',
            'structure_blueprint' => [
                'Tổng quan & Định nghĩa cốt lõi về chủ đề',
                'Phân tích bức tranh toàn cảnh & Phân loại chính',
                'Quy trình / Cơ chế hoạt động chuyên sâu',
                'Các tiêu chí đánh giá & Lựa chọn giải pháp tối ưu',
                'Những sai lầm phổ biến & Cách phòng tránh',
                'Xu hướng mới nhất & Lời khuyên từ góc nhìn chuyên môn',
                'Câu hỏi thường gặp (FAQ toàn diện)',
            ],
        ],
        'topic_cluster' => [
            'key' => 'topic_cluster',
            'label' => 'Topic Cluster (Bài viết vệ tinh chuyên đề)',
            'default_length' => 'standard',
            'structure_blueprint' => [
                'Bối cảnh cụ thể của khía cạnh chuyên sâu',
                'Phân tích chi tiết nguyên nhân / yếu tố kỹ thuật',
                'Hướng dẫn xử lý / Giải pháp áp dụng thực tế',
                'Liên hệ với bức tranh tổng thể (Internal Link về Pillar)',
                'Câu hỏi thường gặp chuyên biệt',
            ],
        ],
        'informational_article' => [
            'key' => 'informational_article',
            'label' => 'Informational Article (Bài viết cung cấp kiến thức)',
            'default_length' => 'standard',
            'structure_blueprint' => [
                'Khái niệm & Bản chất vấn đề người đọc cần hiểu đúng',
                'Nguyên nhân / Các yếu tố tác động chính',
                'Phân tích chi tiết từng khía cạnh quan trọng',
                'Lưu ý thực tế & Khi nào cần tham vấn chuyên gia',
                'Câu hỏi thường gặp (FAQ)',
            ],
        ],
        'commercial_article' => [
            'key' => 'commercial_article',
            'label' => 'Commercial Article (Bài viết tư vấn chọn mua / dịch vụ)',
            'default_length' => 'comprehensive',
            'structure_blueprint' => [
                'Nhu cầu thực tế & Tiêu chí quan trọng khi lựa chọn',
                'Phân tích các giải pháp / phân khúc phổ biến hiện nay',
                'Bảng so sánh ưu nhược điểm & Chi phí tham khảo',
                'Giải pháp phù hợp theo từng nhóm nhu cầu khách hàng',
                'Kinh nghiệm kiểm tra chất lượng & Chính sách bảo đảm',
                'Câu hỏi thường gặp trước khi quyết định',
            ],
        ],
        'transactional_article' => [
            'key' => 'transactional_article',
            'label' => 'Transactional Article (Bài viết chuyển đổi / Báo giá)',
            'default_length' => 'standard',
            'structure_blueprint' => [
                'Thông tin tổng quan về sản phẩm / gói dịch vụ',
                'Cấu trúc chi phí / Các yếu tố quyết định mức giá',
                'Quyền lợi, hạng mục bao gồm & Giá trị nhận được',
                'Quy trình đăng ký / triển khai minh bạch',
                'Chính sách bảo hành, hỗ trợ & Cam kết thực tế',
                'Giải đáp thắc mắc về thanh toán & dịch vụ',
            ],
        ],
        'service_landing_page' => [
            'key' => 'service_landing_page',
            'label' => 'Service Landing Page (Trang đích dịch vụ)',
            'default_length' => 'comprehensive',
            'structure_blueprint' => [
                'Vấn đề khách hàng đang gặp phải & Hệ quả nếu chậm xử lý',
                'Giải pháp dịch vụ chuyên sâu & Điểm khác biệt cốt lõi',
                'Đối tượng phù hợp & Phạm vi triển khai',
                'Quy trình thực hiện tiêu chuẩn từng bước',
                'Tiêu chuẩn chất lượng, Đội ngũ & Chính sách cam kết',
                'Câu hỏi thường gặp & Hướng dẫn liên hệ tư vấn',
            ],
        ],
        'product_review' => [
            'key' => 'product_review',
            'label' => 'Product Review (Đánh giá sản phẩm khách quan)',
            'default_length' => 'standard',
            'structure_blueprint' => [
                'Tổng quan sản phẩm & Đối tượng người dùng mục tiêu',
                'Thông số cốt lõi & Tính năng nổi bật',
                'Đánh giá trải nghiệm thực tế: Ưu điểm & Hạn chế',
                'So sánh nhanh với các lựa chọn cùng phân khúc',
                'Kết luận: Ai nên mua và ai nên cân nhắc lựa chọn khác?',
            ],
        ],
        'product_description' => [
            'key' => 'product_description',
            'label' => 'Product Description (Mô tả sản phẩm chuẩn SEO)',
            'default_length' => 'short',
            'structure_blueprint' => [
                'Đặc điểm nổi bật & Giá trị cốt lõi của sản phẩm',
                'Thông số kỹ thuật / Thành phần / Cấu tạo chi tiết',
                'Công dụng & Lợi ích thiết thực khi sử dụng',
                'Hướng dẫn sử dụng & Bảo quản đúng cách',
                'Chính sách mua hàng, đổi trả & Bảo hành',
            ],
        ],
        'comparison_article' => [
            'key' => 'comparison_article',
            'label' => 'Comparison Article (Bài viết so sánh chuyên sâu)',
            'default_length' => 'comprehensive',
            'structure_blueprint' => [
                'Tổng quan các đối tượng được đặt lên bàn cân so sánh',
                'Bảng so sánh tổng hợp theo các tiêu chí cốt lõi',
                'Phân tích sâu từng tiêu chí (Hiệu quả, Chi phí, Độ bền/Rủi ro)',
                'Trường hợp nào nên chọn phương án A hay phương án B?',
                'Lời khuyên tổng kết giúp ra quyết định chính xác',
            ],
        ],
        'how_to_guide' => [
            'key' => 'how_to_guide',
            'label' => 'How-to Guide (Hướng dẫn thực hành từng bước)',
            'default_length' => 'standard',
            'structure_blueprint' => [
                'Mục tiêu hướng dẫn & Những gì cần chuẩn bị trước khi bắt đầu',
                'Quy trình thực hiện chi tiết từng bước (Step-by-step)',
                'Mẹo tối ưu hiệu quả & Các lỗi thường gặp cần tránh',
                'Cách kiểm tra kết quả sau khi hoàn thành',
                'Câu hỏi thường gặp trong quá trình thực hiện',
            ],
        ],
        'local_seo_article' => [
            'key' => 'local_seo_article',
            'label' => 'Local SEO Article (Bài viết SEO địa phương)',
            'default_length' => 'standard',
            'structure_blueprint' => [
                'Nhu cầu thực tế và đặc thù tại khu vực địa lý mục tiêu',
                'Tiêu chí lựa chọn đơn vị uy tín tại địa phương',
                'Giải pháp & Phạm vi phục vụ tại khu vực của doanh nghiệp',
                'Lưu ý về quy trình, thời gian di chuyển / hỗ trợ tận nơi',
                'Thông tin liên hệ & Giải đáp thắc mắc khu vực',
            ],
        ],
        'news_industry_update' => [
            'key' => 'news_industry_update',
            'label' => 'News / Industry Update (Bản tin & Cập nhật xu hướng ngành)',
            'default_length' => 'short',
            'structure_blueprint' => [
                'Tóm tắt diễn biến / Cập nhật quan trọng mới nhất',
                'Bối cảnh & Nguyên nhân dẫn đến sự thay đổi',
                'Tác động thực tế đến khách hàng và doanh nghiệp trong ngành',
                'Khuyến nghị hành động & Kế hoạch thích ứng kịp thời',
            ],
        ],
        'case_study' => [
            'key' => 'case_study',
            'label' => 'Case Study (Phân tích tình huống & Giải pháp thực tế)',
            'default_length' => 'standard',
            'structure_blueprint' => [
                'Bối cảnh bài toán & Thách thức ban đầu',
                'Phân tích nguyên nhân cốt lõi & Rào cản thực tế',
                'Chiến lược & Quy trình giải pháp được triển khai',
                'Kết quả ghi nhận & Bài học kinh nghiệm rút ra',
            ],
        ],
        'faq_article' => [
            'key' => 'faq_article',
            'label' => 'FAQ Article (Bài viết giải đáp chuyên sâu A-Z)',
            'default_length' => 'standard',
            'structure_blueprint' => [
                'Nhóm câu hỏi cơ bản về bản chất & khái niệm',
                'Nhóm câu hỏi về quy trình, kỹ thuật & độ an toàn',
                'Nhóm câu hỏi về chi phí, thời gian & chính sách',
                'Nhóm câu hỏi xử lý tình huống phát sinh thực tế',
            ],
        ],
    ];

    public const SEARCH_INTENTS = [
        'informational' => 'Informational (Tìm hiểu thông tin, kiến thức, cách làm)',
        'navigational' => 'Navigational (Tìm kiếm thương hiệu, trang web cụ thể)',
        'commercial_investigation' => 'Commercial Investigation (So sánh, đánh giá, cân nhắc trước khi mua)',
        'transactional' => 'Transactional (Sẵn sàng mua hàng, đặt lịch, nhận báo giá)',
        'local_intent' => 'Local Intent (Tìm kiếm dịch vụ/địa điểm theo khu vực địa lý)',
    ];

    public const LENGTH_TIERS = [
        'short' => ['min' => 800, 'max' => 1200, 'target' => 1000, 'label' => 'Ngắn (800–1200 từ)'],
        'standard' => ['min' => 1200, 'max' => 1800, 'target' => 1500, 'label' => 'Tiêu chuẩn (1200–1800 từ)'],
        'comprehensive' => ['min' => 1800, 'max' => 2500, 'target' => 2100, 'label' => 'Chuyên sâu (1800–2500 từ)'],
        'advanced' => ['min' => 2500, 'max' => 3500, 'target' => 2800, 'target_plus' => true, 'label' => 'Chuyên khảo / Pillar (2500–3500+ từ)'],
    ];

    public function __construct(
        protected BrandKnowledgeService $brandKnowledgeService,
        protected IndustryProfileRegistry $industryRegistry
    ) {}

    /**
     * Trả về danh sách cấu hình Content Types, Search Intents và Length Tiers.
     *
     * @return array<string, mixed>
     */
    public function getMetadataCatalog(): array
    {
        return [
            'content_types' => array_values(self::CONTENT_TYPES),
            'search_intents' => self::SEARCH_INTENTS,
            'length_tiers' => self::LENGTH_TIERS,
        ];
    }

    /**
     * Thực hiện quy trình 5 bước phân tích chiến lược trước khi viết bài (Section 5: Step 1 -> Step 5).
     *
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    public function analyzeBeforeWriting(Website $website, Keyword $keyword, array $brief = []): array
    {
        $brandKnowledgeId = isset($brief['brand_knowledge_id']) ? (int) $brief['brand_knowledge_id'] : null;

        // STEP 1: Analyze Business Context
        $businessContext = $this->step1AnalyzeBusinessContext($website, $brandKnowledgeId, $brief);

        // STEP 2: Keyword Analysis & Multi-Intent Detection
        $keywordAnalysis = $this->step2AnalyzeKeywordsAndIntent($keyword, $businessContext, $brief);

        // STEP 3: Audience Analysis
        $audienceAnalysis = $this->step3AnalyzeAudience($keyword, $businessContext, $keywordAnalysis, $brief);

        // STEP 4: Content Strategy (Content Type, Angle, Internal Links, CTA)
        $contentStrategy = $this->step4BuildContentStrategy($website, $keyword, $businessContext, $keywordAnalysis, $audienceAnalysis, $brief);

        // STEP 5: Outline Blueprint Guidelines
        $outlineBlueprint = $this->step5BuildOutlineBlueprint($keyword, $businessContext, $keywordAnalysis, $contentStrategy, $brief);

        return [
            'step_1_business_context' => $businessContext,
            'step_2_keyword_analysis' => $keywordAnalysis,
            'step_3_audience_analysis' => $audienceAnalysis,
            'step_4_content_strategy' => $contentStrategy,
            'step_5_outline_blueprint' => $outlineBlueprint,
        ];
    }

    /**
     * Step 1: Analyze Business Context (Đọc Business Profile & phân tách dữ liệu xác thực vs chưa xác minh).
     *
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    protected function step1AnalyzeBusinessContext(Website $website, ?int $brandKnowledgeId, array $brief): array
    {
        $structured = $this->brandKnowledgeService->extractStructuredContext($website, $brandKnowledgeId);

        // Cho phép ghi đè nhẹ từ Content Generation Form (nếu user chọn sản phẩm/địa điểm/tone cụ thể cho bài này)
        $targetProduct = trim((string) ($brief['target_product_service'] ?? ''));
        $targetAudienceOverride = trim((string) ($brief['target_audience'] ?? ''));
        $writingStyleOverride = trim((string) ($brief['writing_style'] ?? $brief['tone'] ?? ''));
        $localLocation = trim((string) ($brief['local_seo_location'] ?? ''));

        $industryKey = $structured['industry_key'] ?? IndustryProfileRegistry::INDUSTRY_OTHER;
        if ($industryKey === IndustryProfileRegistry::INDUSTRY_OTHER && ! empty($brief['primary_keyword'])) {
            $industryKey = $this->industryRegistry->detectIndustry([
                'primary_industry' => $brief['primary_keyword'].' '.($brief['topic_description'] ?? ''),
                'brand_name' => $structured['brand_name'] ?? '',
            ]);
            $structured['industry_key'] = $industryKey;
            $structured['industry_profile'] = $this->industryRegistry->getProfile($industryKey);
        }

        return [
            'brand_knowledge_id' => $structured['brand_knowledge_id'],
            'brand_name' => $structured['brand_name'] ?: ($website->name ?? $website->domain),
            'industry_key' => $structured['industry_key'],
            'industry_name' => $structured['industry_profile']['name'] ?? 'Đa ngành',
            'ymyl_level' => $structured['industry_profile']['ymyl_level'] ?? 'low',
            'verification_requirement' => $structured['industry_profile']['verification_requirement'] ?? 'standard',
            'business_model' => $structured['business_model'] ?? 'B2C',
            'products_and_services' => $structured['product_catalog'] ?? [],
            'promoted_product_service' => $targetProduct,
            'target_personas' => $targetAudienceOverride !== ''
                ? array_merge([$targetAudienceOverride], $structured['target_personas'] ?? [])
                : ($structured['target_personas'] ?? []),
            'market_and_area' => $localLocation !== ''
                ? $localLocation
                : (($structured['business_profile']['service_area'] ?? 'Việt Nam')),
            'unique_selling_points' => $structured['unique_selling_points'] ?? [],
            'brand_tone' => $writingStyleOverride !== '' ? $writingStyleOverride : ($structured['tone_of_voice'] ?? 'professional'),
            'pronouns_addressing' => $structured['brand_orientation']['pronouns_addressing'] ?? 'Doanh nghiệp - Khách hàng (Bạn)',
            'usable_verified_information' => $structured['tier_a_verified_facts'] ?? [],
            'unverified_information_do_not_fabricate' => $structured['unverified_fields'] ?? [],
            'industry_profile' => $structured['industry_profile'],
        ];
    }

    /**
     * Step 2: Keyword Analysis (Primary, Secondary, Semantic, Long-tail, Primary & Secondary Search Intents, Entities).
     *
     * @param  array<string, mixed>  $businessContext
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    protected function step2AnalyzeKeywordsAndIntent(Keyword $keyword, array $businessContext, array $brief): array
    {
        $primaryKeyword = trim((string) ($brief['primary_keyword'] ?? $keyword->keyword));
        $secondaryKeywords = $this->normalizeStringList($brief['secondary_keywords'] ?? []);
        $relatedKeywords = $this->normalizeStringList($brief['related_keywords'] ?? []);
        $localLocation = trim((string) ($brief['local_seo_location'] ?? ''));

        $intentDetection = $this->detectSearchIntents(
            primaryKeyword: $primaryKeyword,
            topicDescription: (string) ($brief['topic_description'] ?? ''),
            explicitIntent: (string) ($brief['search_intent'] ?? $keyword->intent ?? ''),
            localLocation: $localLocation,
            industryProfile: $businessContext['industry_profile'] ?? []
        );

        $domainTerms = $businessContext['industry_profile']['domain_terminology'] ?? [];
        $semanticKeywords = array_values(array_unique(array_merge(
            $relatedKeywords,
            array_slice($domainTerms, 0, 6)
        )));

        $longTailSuggestions = $this->generateLongTailAngles($primaryKeyword, $localLocation, $intentDetection['primary_intent']);

        return [
            'primary_keyword' => $primaryKeyword,
            'secondary_keywords' => $secondaryKeywords,
            'semantic_keywords' => $semanticKeywords,
            'long_tail_keywords' => $longTailSuggestions,
            'primary_search_intent' => $intentDetection['primary_intent'],
            'secondary_search_intents' => $intentDetection['secondary_intents'],
            'related_entities' => $domainTerms,
            'local_seo_modifier' => $localLocation,
            'anti_stuffing_rule' => 'Sử dụng từ khóa tự nhiên theo ngữ cảnh ngữ nghĩa (Semantic SEO), tuyệt đối không lặp từ khóa máy móc hoặc ép mật độ phần trăm cứng nhắc.',
        ];
    }

    /**
     * Step 3: Audience Analysis (Ai tìm kiếm, vấn đề, nhu cầu, bằng chứng cần thiết, rào cản mua hàng, yếu tố tin cậy).
     *
     * @param  array<string, mixed>  $businessContext
     * @param  array<string, mixed>  $keywordAnalysis
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    protected function step3AnalyzeAudience(Keyword $keyword, array $businessContext, array $keywordAnalysis, array $brief): array
    {
        $personas = $businessContext['target_personas'] ?? [];
        $primaryIntent = $keywordAnalysis['primary_search_intent'] ?? 'informational';
        $ymylLevel = $businessContext['ymyl_level'] ?? 'low';

        $whoIsSearching = ! empty($personas)
            ? implode(', ', $personas)
            : 'Người dùng hoặc khách hàng tiềm năng đang quan tâm trực tiếp đến "'.$keywordAnalysis['primary_keyword'].'"';

        $problemsSolved = $businessContext['usable_verified_information']['problems_solved']
            ?? ($businessContext['industry_profile']['sensitive_claims_to_control'][0] ?? 'Cần thông tin chính xác, minh bạch để giải quyết vấn đề hoặc đưa ra quyết định đúng đắn.');

        $evidenceNeeded = $ymylLevel === 'high'
            ? 'Căn cứ chuyên môn xác thực, quy trình tiêu chuẩn, phân tích lợi ích & rủi ro khách quan, minh bạch pháp lý/chuyên môn.'
            : 'Thông số thực tế, so sánh ưu nhược điểm rõ ràng, quy trình triển khai cụ thể và chính sách bảo hành/hỗ trợ minh bạch.';

        $purchaseBarriers = match ($primaryIntent) {
            'transactional', 'commercial_investigation' => 'Lo ngại về chi phí ẩn, chất lượng thực tế có đúng như quảng cáo không, chế độ bảo hành/hậu mãi và độ uy tín của đơn vị cung cấp.',
            'local_intent' => 'Khoảng cách địa lý, sự thuận tiện khi di chuyển/liên hệ, uy tín thực tế tại địa phương và thời gian phản hồi.',
            default => 'Thông tin trên mạng chung chung hoặc mâu thuẫn, khó phân biệt đâu là giải pháp phù hợp với tình huống cụ thể của bản thân.',
        };

        return [
            'searcher_persona' => $whoIsSearching,
            'core_problem' => $problemsSolved,
            'information_need' => ! empty($brief['topic_description'])
                ? (string) $brief['topic_description']
                : 'Hiểu rõ bản chất, tiêu chí đánh giá và hướng giải quyết khả thi cho chủ đề "'.$keywordAnalysis['primary_keyword'].'".',
            'required_evidence' => $evidenceNeeded,
            'decision_barriers' => $purchaseBarriers,
            'trust_builders' => 'Nội dung trung thực, không phóng đại, phân tích đa chiều, nêu rõ điều kiện áp dụng và sử dụng đúng dữ liệu đã xác minh của '.$businessContext['brand_name'].'.',
        ];
    }

    /**
     * Step 4: Content Strategy (Content Type, Góc tiếp cận, Thông điệp, Internal Links, CTA).
     *
     * @param  array<string, mixed>  $businessContext
     * @param  array<string, mixed>  $keywordAnalysis
     * @param  array<string, mixed>  $audienceAnalysis
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    protected function step4BuildContentStrategy(
        Website $website,
        Keyword $keyword,
        array $businessContext,
        array $keywordAnalysis,
        array $audienceAnalysis,
        array $brief
    ): array {
        $requestedType = trim((string) ($brief['content_type'] ?? ''));
        $contentTypeKey = ($requestedType !== '' && $requestedType !== 'auto' && isset(self::CONTENT_TYPES[$requestedType]))
            ? $requestedType
            : $this->proposeContentType(
                primaryKeyword: $keywordAnalysis['primary_keyword'],
                primaryIntent: $keywordAnalysis['primary_search_intent'],
                localLocation: $keywordAnalysis['local_seo_modifier'],
                wordCountTier: (string) ($brief['desired_length'] ?? '')
            );

        $contentTypeConfig = self::CONTENT_TYPES[$contentTypeKey] ?? self::CONTENT_TYPES['informational_article'];

        // Xác định độ dài mục tiêu (Section 6.2)
        $lengthKey = trim((string) ($brief['desired_length'] ?? ''));
        if (! isset(self::LENGTH_TIERS[$lengthKey])) {
            $lengthKey = $contentTypeConfig['default_length'] ?? 'standard';
        }
        $lengthTier = self::LENGTH_TIERS[$lengthKey];

        // Lấy cơ hội Internal Linking thực tế từ DB + URL do user nhập trong form
        $internalLinkOpportunities = $this->resolveInternalLinkOpportunities($website, $keyword, $brief);

        // Xác định CTA phù hợp
        $appropriateCtas = $businessContext['industry_profile']['appropriate_ctas'] ?? ['Liên hệ tư vấn chi tiết'];
        $selectedCta = ! empty($brief['desired_cta'])
            ? trim((string) $brief['desired_cta'])
            : (! empty($businessContext['usable_verified_information']['priority_cta'])
                ? $businessContext['usable_verified_information']['priority_cta']
                : ($appropriateCtas[0] ?? 'Liên hệ với '.$businessContext['brand_name'].' để được tư vấn chi tiết'));

        return [
            'content_type' => $contentTypeKey,
            'content_type_label' => $contentTypeConfig['label'],
            'length_tier' => $lengthKey,
            'target_word_count_range' => [$lengthTier['min'], $lengthTier['max']],
            'target_word_count' => $lengthTier['target'],
            'article_goal' => ! empty($brief['article_goal'])
                ? trim((string) $brief['article_goal'])
                : 'Giải quyết trọn vẹn nhu cầu tìm kiếm cho từ khóa "'.$keywordAnalysis['primary_keyword'].'" đồng thời xây dựng uy tín chuyên môn cho '.$businessContext['brand_name'].'.',
            'differentiated_angle' => 'Đi thẳng vào trọng tâm chuyên môn, cung cấp tiêu chí đánh giá thực tế, bảng biểu/so sánh trực quan và lưu ý an toàn thay vì viết lý thuyết sáo rỗng.',
            'core_message' => $audienceAnalysis['trust_builders'],
            'structure_blueprint' => $contentTypeConfig['structure_blueprint'],
            'internal_link_opportunities' => $internalLinkOpportunities,
            'reference_urls' => $this->normalizeStringList($brief['reference_urls'] ?? []),
            'competitor_urls' => $this->normalizeStringList($brief['competitor_urls'] ?? []),
            'recommended_cta' => $selectedCta,
            'desired_image_count' => isset($brief['desired_image_count']) ? max(1, min(10, (int) $brief['desired_image_count'])) : 3,
            'special_requirements' => trim((string) ($brief['special_requirements'] ?? '')),
            'suggested_schemas' => $this->resolveSuggestedSchemas($contentTypeKey, $businessContext['industry_profile'] ?? []),
        ];
    }

    /**
     * Step 5: Build Article Outline Blueprint (Nguyên tắc dựng dàn ý có chiều sâu, không trùng lặp).
     *
     * @param  array<string, mixed>  $businessContext
     * @param  array<string, mixed>  $keywordAnalysis
     * @param  array<string, mixed>  $contentStrategy
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    protected function step5BuildOutlineBlueprint(
        Keyword $keyword,
        array $businessContext,
        array $keywordAnalysis,
        array $contentStrategy,
        array $brief
    ): array {
        $industryStructures = $businessContext['industry_profile']['recommended_structures'] ?? [];
        $industryHint = [];
        if (! empty($industryStructures)) {
            $firstKey = array_key_first($industryStructures);
            $industryHint = $industryStructures[$firstKey] ?? [];
        }

        return [
            'content_type' => $contentStrategy['content_type'],
            'recommended_h2_flow' => $contentStrategy['structure_blueprint'],
            'industry_specific_flow_hint' => $industryHint,
            'outline_rules' => [
                'Mỗi H2 giải quyết một khía cạnh độc lập, tuyệt đối không trùng lặp ý giữa các H2/H3.',
                'Không tạo các thẻ H2/H3 vô nghĩa chỉ để nhồi nhét từ khóa chính.',
                'Thứ tự thông tin đi từ giải quyết trực diện Search Intent chính -> phân tích chuyên sâu -> tiêu chí/so sánh thực tế -> lưu ý/rủi ro -> FAQ -> Kết luận & CTA.',
                'Độ sâu chuyên môn phải tương xứng với loại bài '.$contentStrategy['content_type_label'].' và độ dài mục tiêu '.$contentStrategy['target_word_count'].' từ.',
            ],
        ];
    }

    /**
     * Xây dựng khối văn bản hướng dẫn Chiến lược 5 bước để nhúng vào Prompt của OutlineAgent & WritingAgent.
     *
     * @param  array<string, mixed>  $strategy
     */
    public function buildStrategyPromptBlock(array $strategy): string
    {
        $s1 = $strategy['step_1_business_context'] ?? [];
        $s2 = $strategy['step_2_keyword_analysis'] ?? [];
        $s3 = $strategy['step_3_audience_analysis'] ?? [];
        $s4 = $strategy['step_4_content_strategy'] ?? [];
        $s5 = $strategy['step_5_outline_blueprint'] ?? [];

        $lines = [];
        $lines[] = '=== AI CONTENT STRATEGY ENGINE (5-STEP PRE-WRITING ANALYSIS) ===';
        $lines[] = '[STEP 1: BUSINESS & INDUSTRY CONTEXT]';
        $lines[] = '- Brand / Business: '.($s1['brand_name'] ?? 'N/A').' (Model: '.($s1['business_model'] ?? 'B2C').')';
        $lines[] = '- Industry Profile: '.($s1['industry_name'] ?? 'General').' | YMYL Level: '.strtoupper((string) ($s1['ymyl_level'] ?? 'low'));
        $lines[] = '- Market / Location: '.($s1['market_and_area'] ?? 'Việt Nam');
        $lines[] = '- Tone & Addressing: '.($s1['brand_tone'] ?? 'professional').' | Xưng hô: '.($s1['pronouns_addressing'] ?? 'Doanh nghiệp / Bạn');
        if (! empty($s1['promoted_product_service'])) {
            $lines[] = '- Featured Product/Service for this Article: '.$s1['promoted_product_service'];
        }
        if (! empty($s1['unverified_information_do_not_fabricate'])) {
            $lines[] = '- UNVERIFIED FIELDS (STRICTLY DO NOT FABRICATE): '.implode(', ', $s1['unverified_information_do_not_fabricate']);
        }

        $lines[] = '';
        $lines[] = '[STEP 2: KEYWORD & MULTI-INTENT ANALYSIS]';
        $lines[] = '- Primary Keyword: '.($s2['primary_keyword'] ?? '');
        if (! empty($s2['secondary_keywords'])) {
            $lines[] = '- Secondary Keywords: '.implode(', ', $s2['secondary_keywords']);
        }
        if (! empty($s2['semantic_keywords'])) {
            $lines[] = '- Semantic Keywords & Entities: '.implode(', ', $s2['semantic_keywords']);
        }
        $lines[] = '- Primary Search Intent: '.($s2['primary_search_intent'] ?? 'informational');
        if (! empty($s2['secondary_search_intents'])) {
            $lines[] = '- Secondary Search Intents: '.implode(', ', $s2['secondary_search_intents']);
        }
        if (! empty($s2['local_seo_modifier'])) {
            $lines[] = '- Local SEO Target Location: '.$s2['local_seo_modifier'];
        }

        $lines[] = '';
        $lines[] = '[STEP 3: TARGET AUDIENCE & TRUST ANALYSIS]';
        $lines[] = '- Target Reader Persona: '.($s3['searcher_persona'] ?? '');
        $lines[] = '- Core Problem & Information Need: '.($s3['information_need'] ?? '');
        $lines[] = '- Evidence Needed by Reader: '.($s3['required_evidence'] ?? '');
        $lines[] = '- Decision Barriers to Address: '.($s3['decision_barriers'] ?? '');

        $lines[] = '';
        $lines[] = '[STEP 4: CONTENT TYPE & EXECUTION STRATEGY]';
        $lines[] = '- Selected Content Type: '.($s4['content_type_label'] ?? $s4['content_type'] ?? 'informational_article');
        $lines[] = '- Target Length Tier: '.($s4['length_tier'] ?? 'standard').' ('.($s4['target_word_count_range'][0] ?? 1200).' - '.($s4['target_word_count_range'][1] ?? 1800).' words)';
        $lines[] = '- Article Goal: '.($s4['article_goal'] ?? '');
        $lines[] = '- Differentiated Angle: '.($s4['differentiated_angle'] ?? '');
        $lines[] = '- Recommended CTA: '.($s4['recommended_cta'] ?? '');
        if (! empty($s4['special_requirements'])) {
            $lines[] = '- Special Editorial Requirements: '.$s4['special_requirements'];
        }
        if (! empty($s4['reference_urls'])) {
            $lines[] = '- Verified Reference Sources/URLs: '.implode(', ', $s4['reference_urls']);
        }
        if (! empty($s4['internal_link_opportunities'])) {
            $linkStrs = [];
            foreach ($s4['internal_link_opportunities'] as $link) {
                $linkStrs[] = ($link['anchor'] ?? $link['title'] ?? 'Bài viết liên quan').' -> '.($link['url'] ?? '');
            }
            $lines[] = '- Internal Linking Opportunities (Use Natural Anchor Text): '.implode(' | ', $linkStrs);
        }

        $lines[] = '';
        $lines[] = '[STEP 5: RECOMMENDED STRUCTURAL FLOW FOR THIS CONTENT TYPE]';
        foreach (($s5['recommended_h2_flow'] ?? []) as $idx => $stepTitle) {
            $lines[] = '  '.($idx + 1).'. '.$stepTitle;
        }
        if (! empty($s5['industry_specific_flow_hint'])) {
            $lines[] = '- Industry Structure Nuances: '.implode(' -> ', $s5['industry_specific_flow_hint']);
        }

        return implode("\n", $lines);
    }

    /**
     * Phát hiện ý định tìm kiếm chính (Primary Intent) và các ý định phụ (Secondary Intents).
     *
     * @param  array<string, mixed>  $industryProfile
     * @return array{primary_intent: string, secondary_intents: array<int, string>}
     */
    public function detectSearchIntents(
        string $primaryKeyword,
        string $topicDescription = '',
        string $explicitIntent = '',
        string $localLocation = '',
        array $industryProfile = []
    ): array {
        $text = mb_strtolower($primaryKeyword.' '.$topicDescription.' '.$localLocation, 'UTF-8');

        $scores = [
            'informational' => 2,
            'commercial_investigation' => 1,
            'transactional' => 0,
            'local_intent' => 0,
            'navigational' => 0,
        ];

        if ($localLocation !== '' || preg_match('/(tại|ở|quận|huyện|tphcm|hà nội|đà nẵng|gần đây|địa chỉ|phòng khám|chi nhánh)/u', $text)) {
            $scores['local_intent'] += 5;
        }

        if (preg_match('/(bảng giá|giá bao nhiêu|chi phí|mua|đặt lịch|đăng ký|thuê|báo giá|khuyến mãi|trọn gói)/u', $text)) {
            $scores['transactional'] += 5;
            $scores['commercial_investigation'] += 3;
        }

        if (preg_match('/(so sánh|review|đánh giá|tốt nhất|nên chọn|loại nào tốt|top|ưu nhược điểm|kinh nghiệm chọn)/u', $text)) {
            $scores['commercial_investigation'] += 6;
        }

        if (preg_match('/(là gì|cách|hướng dẫn|quy trình|nguyên nhân|dấu hiệu|thủ tục|điều kiện|kinh nghiệm|cẩm nang)/u', $text)) {
            $scores['informational'] += 5;
        }

        if ($explicitIntent !== '' && isset(self::SEARCH_INTENTS[$explicitIntent])) {
            $scores[$explicitIntent] += 10;
        } elseif ($explicitIntent === 'commercial') {
            $scores['commercial_investigation'] += 10;
        }

        arsort($scores);
        $sortedKeys = array_keys($scores);
        $primaryIntent = $sortedKeys[0] ?? 'informational';

        $secondaryIntents = [];
        foreach (array_slice($sortedKeys, 1) as $intentKey) {
            if ($scores[$intentKey] >= 2) {
                $secondaryIntents[] = $intentKey;
            }
        }

        if (empty($secondaryIntents)) {
            $secondaryIntents[] = $primaryIntent === 'informational' ? 'commercial_investigation' : 'informational';
        }

        return [
            'primary_intent' => $primaryIntent,
            'secondary_intents' => array_slice($secondaryIntents, 0, 2),
        ];
    }

    /**
     * Tự động đề xuất Content Type phù hợp nhất từ 14 loại nội dung (Section 4.1).
     */
    public function proposeContentType(
        string $primaryKeyword,
        string $primaryIntent,
        string $localLocation = '',
        string $wordCountTier = ''
    ): string {
        $kw = mb_strtolower($primaryKeyword, 'UTF-8');

        if ($wordCountTier === 'advanced' || preg_match('/(toàn tập|cẩm nang toàn diện|tất tần tật|a-z)/u', $kw)) {
            return 'pillar_page';
        }
        if (preg_match('/(so sánh|vs|khác nhau|nên chọn .+ hay)/u', $kw)) {
            return 'comparison_article';
        }
        if (preg_match('/(review|đánh giá chi tiết|trải nghiệm)/u', $kw)) {
            return 'product_review';
        }
        if (preg_match('/(cách|hướng dẫn|quy trình|thủ tục|các bước)/u', $kw)) {
            return 'how_to_guide';
        }
        if (preg_match('/(câu hỏi thường gặp|faq|hỏi đáp|giải đáp)/u', $kw)) {
            return 'faq_article';
        }
        if (preg_match('/(case study|tình huống thực tế|câu chuyện khách hàng|dự án thực tế)/u', $kw)) {
            return 'case_study';
        }
        if (preg_match('/(tin tức|xu hướng|cập nhật mới|thị trường năm)/u', $kw)) {
            return 'news_industry_update';
        }
        if ($localLocation !== '' || $primaryIntent === 'local_intent') {
            return 'local_seo_article';
        }
        if ($primaryIntent === 'transactional') {
            if (preg_match('/(dịch vụ|설치|thi công|tư vấn|phòng khám|luật sư)/u', $kw)) {
                return 'service_landing_page';
            }

            return 'transactional_article';
        }
        if ($primaryIntent === 'commercial_investigation') {
            return 'commercial_article';
        }

        return 'informational_article';
    }

    /**
     * Tìm các cơ hội liên kết nội bộ (Internal Links) từ URL do người dùng chỉ định và các bài viết đã có trên Website.
     *
     * @param  array<string, mixed>  $brief
     * @return array<int, array{title: string, anchor: string, url: string}>
     */
    protected function resolveInternalLinkOpportunities(Website $website, Keyword $keyword, array $brief): array
    {
        $links = [];

        // 1. URL nội bộ người dùng nhập trực tiếp trong form tạo bài viết
        $userInternalUrls = $this->normalizeStringList($brief['internal_link_urls'] ?? []);
        foreach ($userInternalUrls as $rawUrl) {
            if (str_contains($rawUrl, '|')) {
                [$anchor, $url] = array_map('trim', explode('|', $rawUrl, 2));
                $links[] = ['title' => $anchor, 'anchor' => $anchor, 'url' => $url];
            } else {
                $slugPart = trim((string) parse_url($rawUrl, PHP_URL_PATH), '/');
                $anchor = $slugPart !== '' ? str_replace('-', ' ', basename($slugPart)) : $rawUrl;
                $links[] = ['title' => $anchor, 'anchor' => $anchor, 'url' => $rawUrl];
            }
        }

        // 2. Lấy thêm các bài viết cùng Website để gợi ý liên kết nội bộ tự nhiên
        $existingArticles = Article::query()
            ->where('website_id', $website->id)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereNotNull('title')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'focus_keyword', 'wordpress_post_url']);

        $baseUrl = rtrim($website->url ?: ('https://'.$website->domain), '/');
        foreach ($existingArticles as $art) {
            if ($art->keyword_id === $keyword->id) {
                continue;
            }
            $url = $art->wordpress_post_url ?: ($baseUrl.'/'.ltrim((string) $art->slug, '/'));
            $links[] = [
                'title' => (string) $art->title,
                'anchor' => (string) ($art->focus_keyword ?: $art->title),
                'url' => $url,
            ];
            if (count($links) >= 6) {
                break;
            }
        }

        return $links;
    }

    /**
     * Đề xuất loại Schema.org phù hợp với Content Type & Industry Profile (Section 9.4).
     *
     * @param  array<string, mixed>  $industryProfile
     * @return array<int, string>
     */
    protected function resolveSuggestedSchemas(string $contentType, array $industryProfile): array
    {
        $schemas = ['Article', 'BreadcrumbList'];

        if (in_array($contentType, ['service_landing_page', 'local_seo_article'], true)) {
            $schemas[] = 'Service';
            if ($contentType === 'local_seo_article') {
                $schemas[] = 'LocalBusiness';
            }
        } elseif (in_array($contentType, ['product_review', 'product_description'], true)) {
            $schemas[] = 'Product';
        } else {
            $schemas[] = 'BlogPosting';
        }

        $schemas[] = 'FAQPage';

        $industrySchemas = $industryProfile['recommended_schemas'] ?? [];
        foreach ($industrySchemas as $indSchema) {
            if (! in_array($indSchema, $schemas, true)) {
                $schemas[] = $indSchema;
            }
        }

        return array_values(array_unique($schemas));
    }

    /**
     * @return array<int, string>
     */
    protected function generateLongTailAngles(string $primaryKeyword, string $localLocation, string $primaryIntent): array
    {
        $list = [];
        if ($localLocation !== '') {
            $list[] = $primaryKeyword.' tại '.$localLocation;
        }
        $list[] = 'kinh nghiệm '.$primaryKeyword;
        if (in_array($primaryIntent, ['commercial_investigation', 'transactional'], true)) {
            $list[] = 'chi phí '.$primaryKeyword.' mới nhất';
            $list[] = 'tiêu chí lựa chọn '.$primaryKeyword.' uy tín';
        } else {
            $list[] = 'quy trình '.$primaryKeyword.' chuẩn chuyên môn';
            $list[] = 'những lưu ý quan trọng khi '.$primaryKeyword;
        }

        return array_values(array_unique($list));
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeStringList(mixed $input): array
    {
        if (is_string($input)) {
            $parts = preg_split('/[,;\n]+/', $input) ?: [];

            return array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));
        }

        if (is_array($input)) {
            $result = [];
            foreach ($input as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $result[] = trim($item);
                }
            }

            return array_values(array_unique($result));
        }

        return [];
    }
}
