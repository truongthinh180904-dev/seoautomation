<?php

namespace App\Services\Chatbot;

use App\DTOs\Chatbot\ExecutionPlanDTO;
use App\DTOs\Chatbot\UserIntentDTO;
use App\Models\BrandKnowledgeBase;
use App\Models\Conversation;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Brand\BrandKnowledgeService;
use Illuminate\Support\Str;

class AiOrchestratorService
{
    /**
     * Danh sách 10 công cụ SEO chuẩn hóa được kết nối từ Phase 1 và Phase 2.
     *
     * @var array<string, array{name: string, description: string, estimated_credits: int, is_sensitive: bool}>
     */
    public const AVAILABLE_TOOLS = [
        'keyword_research' => [
            'name' => 'Nghiên cứu & Khám phá Từ khóa',
            'description' => 'Tìm kiếm ý tưởng từ khóa, volume, KD, search intent và gom cụm ngữ nghĩa tự động.',
            'estimated_credits' => 2,
            'is_sensitive' => false,
        ],
        'competitor_analysis' => [
            'name' => 'Phân tích Top 3 Đối thủ Google SERP',
            'description' => 'Phân tích chuyên sâu Top 3 đối thủ Google SERP, lấy đối thủ #1 làm mỏ neo chuẩn xác (Anchor Benchmark) về số từ, mật độ LSI Entities và cấu trúc Headings.',
            'estimated_credits' => 3,
            'is_sensitive' => false,
        ],
        'topic_cluster' => [
            'name' => 'Thiết lập Topic Cluster',
            'description' => 'Xây dựng cấu trúc cụm chủ đề đa tầng (Pillar - Cluster), phân bổ bài vệ tinh chống cannibalization.',
            'estimated_credits' => 2,
            'is_sensitive' => false,
        ],
        'content_gap' => [
            'name' => 'Phát hiện Lỗ hổng Nội dung (Content Gap)',
            'description' => 'So sánh ma trận từ khóa của website với đối thủ để tìm các chủ đề đối thủ đang xếp hạng cao mà website chưa có.',
            'estimated_credits' => 2,
            'is_sensitive' => false,
        ],
        'content_studio_write' => [
            'name' => 'Soạn thảo Bài viết Chuẩn SEO (Content Studio)',
            'description' => 'Khởi tạo bài viết dài 1.500 - 3.500 từ chuẩn SEO, nhúng thực thể LSI, định dạng Markdown theo Brand Voice.',
            'estimated_credits' => 20,
            'is_sensitive' => false,
        ],
        'seo_score' => [
            'name' => 'Chấm điểm SEO 100 Điểm (SeoScoringEngine)',
            'description' => 'Đánh giá chất lượng toàn diện bài viết theo thang điểm 100 qua 14 tiêu chí kỹ thuật SEO thuần PHP (tức thì, 0 credit).',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'internal_links' => [
            'name' => 'Đồ thị Liên kết Nội bộ (Internal Linking)',
            'description' => 'Quét kho bài viết của website và tự động gợi ý neo liên kết theo ngữ cảnh thực thể liên quan.',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'stock_image_free' => [
            'name' => 'Tìm ảnh Stock Thương mại Miễn phí Bản quyền',
            'description' => 'Quét ảnh chất lượng cao 100% không bản quyền từ Unsplash/Pexels theo ngữ cảnh H2, tự động nén WebP dưới 150KB.',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'ai_image' => [
            'name' => 'Tạo ảnh AI Gemini Imagen 3',
            'description' => 'Render ảnh AI chuẩn xác theo bối cảnh H2 khi kho ảnh stock không có ảnh phù hợp, tự động nén WebP < 150KB.',
            'estimated_credits' => 5,
            'is_sensitive' => false,
        ],
        'bulk_generation' => [
            'name' => 'Sản xuất Nội dung Hàng loạt (Bulk Generation)',
            'description' => 'Tạo hàng loạt bài viết theo danh sách từ khóa lớn qua hàng đợi Redis Queue chuyên dụng.',
            'estimated_credits' => 18,
            'is_sensitive' => true,
        ],
        'wordpress_publish' => [
            'name' => 'Xuất bản Bài viết Lên WordPress',
            'description' => 'Đẩy bài viết và ảnh trực tiếp lên website WordPress mục tiêu qua REST API có xác thực.',
            'estimated_credits' => 0,
            'is_sensitive' => true,
        ],
        'gsc_query' => [
            'name' => 'Tra cứu Google Search Console',
            'description' => 'Tra cứu số liệu Clicks, Impressions, CTR, Vị trí trung bình, Top Queries và Top Pages từ Google Search Console (0 credit, tức thì).',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'gsc_performance' => [
            'name' => 'Tra cứu Google Search Console',
            'description' => 'Tra cứu số liệu Clicks, Impressions, CTR, Vị trí trung bình, Top Queries và Top Pages từ Google Search Console (0 credit, tức thì).',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'ga4_query' => [
            'name' => 'Tra cứu Google Analytics 4',
            'description' => 'Tra cứu số liệu Sessions, Active Users, Engagement Rate, Thời lượng phiên trung bình và Conversions theo Landing Page từ GA4 (0 credit, tức thì).',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'ga4_traffic' => [
            'name' => 'Tra cứu Google Analytics 4',
            'description' => 'Tra cứu số liệu Sessions, Active Users, Engagement Rate, Thời lượng phiên trung bình và Conversions theo Landing Page từ GA4 (0 credit, tức thì).',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'rank_tracker' => [
            'name' => 'Kiểm tra Thứ hạng Từ khóa (Rank Tracker)',
            'description' => 'Kiểm tra thứ hạng từ khóa mục tiêu trong Top 100 Google SERP, biến động tăng/giảm vị trí và lịch sử thứ hạng theo ngày (0 credit, tức thì).',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'seo_intelligence' => [
            'name' => 'Phát hiện Cơ hội & Bất thường SEO (SEO Intelligence)',
            'description' => 'Tự động phân tích và giải thích 6 dạng bất thường & cơ hội SEO (Tụt hạng, Tụt traffic, CTR thấp, Striking Distance, Cannibalization) kèm điểm tiềm năng Opportunity Score (0 credit, tức thì).',
            'estimated_credits' => 0,
            'is_sensitive' => false,
        ],
        'content_refresh' => [
            'name' => 'Làm mới & Tối ưu Bài viết Cũ (AI Content Refresh)',
            'description' => 'Thu thập bối cảnh GSC/GA4/Rank để AI đề xuất làm mới bài viết cũ, tạo bản so sánh Diff Trước/Sau 2 cột, nâng điểm SEO và chuẩn bị phê duyệt xuất bản WordPress.',
            'estimated_credits' => 2,
            'is_sensitive' => false,
        ],
    ];

    public function __construct(
        protected IntentParserService $intentParser,
        protected ?BrandKnowledgeService $brandKnowledgeService = null
    ) {}

    /**
     * Lấy danh sách 10 công cụ SEO sẵn có.
     *
     * @return array<string, array{name: string, description: string, estimated_credits: int, is_sensitive: bool}>
     */
    public function getAvailableTools(): array
    {
        return self::AVAILABLE_TOOLS;
    }

    /**
     * Xây dựng System Prompt định hình vai trò Senior SEO Specialist & Content Strategist,
     * nhúng mô tả năng lực của 10 công cụ SEO và nạp kiến thức thương hiệu BrandKnowledgeBase.
     */
    public function buildSystemPrompt(Website|Workspace|null $website = null, ?BrandKnowledgeBase $brandKnowledge = null): string
    {
        $workspace = $website instanceof Workspace ? $website : ($website instanceof Website ? $website->workspace : null);
        $websiteModel = $website instanceof Website ? $website : ($workspace ? $workspace->websites()->first() : null);

        $lines = [];

        // 1. Định vị vai trò & Nguyên tắc Senior SEO
        $lines[] = '### ROLE & IDENTITY:';
        $lines[] = 'Bạn là Aura SEO Assistant — Chuyên gia Chiến lược Nội dung & Senior SEO Specialist hàng đầu được tích hợp trong nền tảng SaaS Viết Bài SEO Tự Động.';
        $lines[] = 'Nhiệm vụ của bạn là đồng hành cùng khách hàng từ khâu nghiên cứu từ khóa, cào và giải mã đối thủ, lập cụm chủ đề Topic Cluster, viết bài chuyên sâu chuẩn Semantic SEO & EEAT, cho tới xuất bản lên WordPress.';
        $lines[] = '';

        // 2. Nguyên tắc vận hành cốt lõi
        $lines[] = '### CORE OPERATING PRINCIPLES:';
        $lines[] = '1. Phân tích đối thủ: Tập trung tối đa vào Top 3 đối thủ Google SERP, lấy đối thủ xếp hạng #1 làm thước đo chuẩn xác (Anchor Benchmark) về số lượng từ, mật độ thực thể LSI và cấu trúc thẻ Heading.';
        $lines[] = '2. Xử lý ảnh 3 tầng: Ưu tiên số 1 là ảnh người dùng gửi kèm (đọc hiểu bằng Gemini Vision để ghim đúng H2/H3); Ưu tiên số 2 là tìm ảnh stock 100% không bản quyền (Pexels/Unsplash); Ưu tiên số 3 là AI Gemini render. Mọi ảnh đều bắt buộc nén WebP < 150KB và có thẻ ALT chuẩn SEO.';
        $lines[] = '3. An toàn Credit (Zero Loss): Minh bạch về chi phí credit của từng công cụ. Không bao giờ tự ý kích hoạt các hành động nhạy cảm hoặc tiêu tốn credit lớn mà không có sự xác nhận của người dùng (Human-in-the-Loop).';
        $lines[] = '4. Giao tiếp chuyên nghiệp: Trả lời tự tin, súc tích, có cấu trúc rõ ràng. Nếu người dùng đưa ra yêu cầu mơ hồ hoặc thiếu dữ kiện quan trọng, luôn lịch sự đặt câu hỏi làm rõ trước khi hành động.';
        $lines[] = '5. SEO Automation & Intelligence (Phase 3): Tận dụng dữ liệu thực tế từ Google Search Console (clicks, imp, CTR), GA4 (sessions, users), Rank Tracking (thứ hạng Top 100) và SEO Intelligence Heuristics để chủ động giải thích, tư vấn chiến lược và đề xuất làm mới bài viết cũ (Content Refresh). Khi người dùng đồng ý cập nhật bài viết lên WordPress, luôn yêu cầu xác nhận qua Thẻ Phê Duyệt (Human-in-the-Loop).';
        $lines[] = '';

        // 3. Danh sách công cụ tích hợp sẵn (Tools Registry)
        $lines[] = '### AVAILABLE SEO TOOLS:';
        foreach (self::AVAILABLE_TOOLS as $toolKey => $info) {
            $sensitiveTag = $info['is_sensitive'] ? ' [YÊU CẦU PHÊ DUYỆT TRƯỚC KHI CHẠY]' : '';
            $lines[] = sprintf('- `%s`: %s. %s (Chi phí dự kiến: %d credit)%s',
                $toolKey,
                $info['name'],
                $info['description'],
                $info['estimated_credits'],
                $sensitiveTag
            );
        }
        $lines[] = '';

        // 4. Kiến thức Thương hiệu (Brand Knowledge Base)
        $lines[] = '### BRAND KNOWLEDGE & EDITORIAL CONTEXT:';
        if ($brandKnowledge) {
            $lines[] = $this->formatBrandKnowledge($brandKnowledge);
        } elseif ($websiteModel) {
            $lines[] = sprintf('- Website mục tiêu: %s (%s)', $websiteModel->name, $websiteModel->url ?: $websiteModel->domain);
            if (! empty($websiteModel->brand_voice_summary)) {
                $lines[] = '- Tóm tắt nhận diện thương hiệu: '.$websiteModel->brand_voice_summary;
            } else {
                $lines[] = '- Lưu ý: Website này chưa cấu hình Brand Knowledge Base riêng. Hãy áp dụng văn phong chuẩn SEO chuyên nghiệp, tự nhiên.';
            }
        } else {
            $lines[] = '- Ngữ cảnh chung: Chưa chọn website mục tiêu cụ thể. Khuyên người dùng chọn website để tối ưu Brand Voice và liên kết WordPress chính xác.';
        }

        return implode("\n", $lines);
    }

    /**
     * Tự động nạp kiến thức thương hiệu (BrandKnowledgeBase) của Website hiện tại vào ngữ cảnh.
     * Đảm bảo tính cô lập Multi-Tenancy: Chỉ nạp khi bản ghi thuộc đúng Workspace của phiên chat.
     */
    public function loadBrandKnowledge(Website|int $website, ?int $workspaceId = null): ?BrandKnowledgeBase
    {
        $websiteId = $website instanceof Website ? $website->id : $website;
        $targetWorkspaceId = $workspaceId;

        if ($website instanceof Website && ! $targetWorkspaceId) {
            $targetWorkspaceId = $website->workspace_id;
        }

        $query = BrandKnowledgeBase::query()->where('website_id', $websiteId);

        if ($targetWorkspaceId) {
            $query->where('workspace_id', $targetWorkspaceId);
        }

        // Ưu tiên bản ghi đánh dấu is_default = true, nếu không lấy bản ghi cập nhật mới nhất
        /** @var BrandKnowledgeBase|null $brand */
        $brand = (clone $query)->where('is_default', true)->first()
            ?: $query->orderByDesc('updated_at')->first();

        return $brand;
    }

    /**
     * Lập kế hoạch thực thi nhiều bước (Multi-step Execution Plan) dựa trên UserIntentDTO và Website.
     */
    public function generateExecutionPlan(UserIntentDTO $intent, Website|Workspace|null $website = null): ExecutionPlanDTO
    {
        $planId = (string) Str::uuid();
        $targetWebsite = $website instanceof Website ? $website : ($website instanceof Workspace ? $website->websites()->first() : null);

        // Nếu ý định bị thiếu dữ liệu và cần làm rõ, kế hoạch dừng lại ở trạng thái chờ người dùng
        if ($intent->needsClarification) {
            return new ExecutionPlanDTO(
                id: $planId,
                intent: $intent->intent,
                title: 'Yêu cầu làm rõ thông tin',
                summary: 'Chưa đủ dữ kiện để lập kế hoạch thực thi. Hệ thống đang chờ người dùng cung cấp thêm thông tin.',
                steps: [],
                requiresApproval: false,
                totalEstimatedCredits: 0,
                canExecuteImmediately: false,
                clarificationNeeded: true,
                clarificationMessage: $intent->clarificationQuestion,
                contextMetadata: [
                    'missing_fields' => $intent->missingFields,
                    'raw_query' => $intent->rawQuery,
                ]
            );
        }

        $steps = [];
        $requiresApproval = false;
        $title = 'Kế hoạch thực thi SEO';
        $summary = '';

        switch ($intent->intent) {
            case UserIntentDTO::INTENT_ARTICLE_WRITE:
                $keyword = (string) $intent->getParameter('keyword', 'từ khóa chính');
                $wordCount = (int) $intent->getParameter('word_count', 2000);
                $title = "Kế hoạch soạn thảo bài viết SEO: \"{$keyword}\"";
                $summary = "Quy trình trọn gói 5 bước từ cào Top 3 đối thủ đến chấm điểm bài viết ({$wordCount} từ).";

                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'competitor_analysis',
                        'action' => 'benchmark_top_3_serp',
                        'description' => "Cào và phân tích Top 3 đối thủ Google cho từ khóa \"{$keyword}\", lấy #1 làm mỏ neo chính.",
                        'params' => ['keyword' => $keyword, 'limit' => 3],
                        'status' => 'pending',
                        'estimated_credits' => 3,
                        'requires_approval' => false,
                    ],
                    [
                        'step_number' => 2,
                        'tool' => 'content_studio_write',
                        'action' => 'build_outline',
                        'description' => 'Xây dựng dàn ý chuẩn SEO gồm H2/H3 phủ kín thực thể Semantic Entities và giải quyết trọn vẹn Search Intent.',
                        'params' => ['keyword' => $keyword],
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => false,
                    ],
                    [
                        'step_number' => 3,
                        'tool' => 'content_studio_write',
                        'action' => 'generate_content',
                        'description' => "Soạn thảo bài viết hoàn chỉnh {$wordCount} từ theo Brand Voice của thương hiệu.",
                        'params' => ['keyword' => $keyword, 'word_count' => $wordCount],
                        'status' => 'pending',
                        'estimated_credits' => 20,
                        'requires_approval' => false,
                    ],
                    [
                        'step_number' => 4,
                        'tool' => 'stock_image_free',
                        'action' => 'waterfall_image_processing',
                        'description' => 'Xử lý hình ảnh theo thác nước: Đọc hiểu ảnh đính kèm -> Tìm stock không bản quyền -> AI Imagen render -> Nén WebP < 150KB.',
                        'params' => ['keyword' => $keyword, 'headings_count' => 3],
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => false,
                    ],
                    [
                        'step_number' => 5,
                        'tool' => 'seo_score',
                        'action' => 'audit_article_quality',
                        'description' => 'Chấm điểm SEO 100 điểm, kiểm tra thẻ Heading, mật độ từ khóa và từ cấm Taboo Words.',
                        'params' => ['keyword' => $keyword],
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case UserIntentDTO::INTENT_COMPETITOR_ANALYSIS:
                $keyword = (string) $intent->getParameter('keyword', '');
                $domain = (string) $intent->getParameter('target_domain', '');
                $target = $keyword ? "\"{$keyword}\"" : ($domain ?: 'đối thủ mục tiêu');
                $title = "Kế hoạch phân tích Top 3 SERP đối thủ cho {$target}";
                $summary = 'Thu thập dữ liệu SERP trực tiếp, bóc tách cấu trúc bài viết #1 và so sánh khoảng cách nội dung.';

                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'competitor_analysis',
                        'action' => 'analyze_serp_top3',
                        'description' => "Cào chi tiết Top 3 URL dẫn đầu cho {$target}, đo lường word count và mật độ LSI.",
                        'params' => ['keyword' => $keyword, 'domain' => $domain, 'limit' => 3],
                        'status' => 'pending',
                        'estimated_credits' => 3,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case UserIntentDTO::INTENT_KEYWORD_RESEARCH:
                $seed = (string) $intent->getParameter('seed_keyword', 'chủ đề');
                $count = (int) $intent->getParameter('count', 20);
                $title = "Kế hoạch nghiên cứu {$count} từ khóa hạt giống: \"{$seed}\"";
                $summary = "Truy xuất danh sách {$count} từ khóa liên quan, phân tích Search Intent và gợi ý gom nhóm.";

                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'keyword_research',
                        'action' => 'discover_keywords',
                        'description' => "Tìm kiếm {$count} từ khóa xoay quanh \"{$seed}\", kèm chỉ số volume và độ khó.",
                        'params' => ['seed_keyword' => $seed, 'limit' => $count],
                        'status' => 'pending',
                        'estimated_credits' => 2,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case UserIntentDTO::INTENT_TOPIC_CLUSTER:
                $pillar = (string) $intent->getParameter('pillar_keyword', 'trụ cột');
                $title = "Kế hoạch xây dựng Topic Cluster: \"{$pillar}\"";
                $summary = 'Lập bản đồ cụm chủ đề 3 tầng với bài Pillar trung tâm và 5-10 bài vệ tinh liên kết chéo.';

                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'topic_cluster',
                        'action' => 'create_cluster_structure',
                        'description' => "Xác định Pillar Page \"{$pillar}\" và phân bổ các cụm từ khóa Sub-topics.",
                        'params' => ['pillar_keyword' => $pillar],
                        'status' => 'pending',
                        'estimated_credits' => 2,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case UserIntentDTO::INTENT_ARTICLE_REWRITE:
                $section = (string) $intent->getParameter('target_section', 'đoạn văn');
                $title = "Kế hoạch biên tập & tối ưu {$section}";
                $summary = 'Nâng cấp chất lượng diễn đạt, tối ưu tính hấp dẫn và nhúng các từ khóa thực thể chuẩn Brand Voice.';

                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'content_studio_write',
                        'action' => 'rewrite_section',
                        'description' => "Tối ưu lại {$section} theo văn phong chuyên gia và tuân thủ Brand Voice.",
                        'params' => $intent->parameters,
                        'status' => 'pending',
                        'estimated_credits' => 1,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case UserIntentDTO::INTENT_SEO_SCORE:
                $title = 'Kế hoạch kiểm tra & chấm điểm SEO 100 điểm';
                $summary = 'Quét toàn bộ cấu trúc bài viết qua 14 tiêu chí kỹ thuật SEO thuần PHP (0 credit).';

                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'seo_score',
                        'action' => 'evaluate_article',
                        'description' => 'Kiểm tra độ dài, mật độ từ khóa, thẻ headings, alt ảnh và từ cấm Taboo Words.',
                        'params' => $intent->parameters,
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case UserIntentDTO::INTENT_BULK_GENERATION:
                $count = (int) $intent->getParameter('count', 10);
                $title = "Kế hoạch sản xuất hàng loạt lô {$count} bài viết";
                $summary = "Tạo {$count} bài viết theo lô và đưa vào hàng đợi Redis Queue xử lý bất đồng bộ.";
                $requiresApproval = true; // Hành động nhạy cảm tiêu hao nhiều credit

                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'bulk_generation',
                        'action' => 'queue_bulk_batch',
                        'description' => "Đưa {$count} từ khóa vào hàng đợi Queue Redis, áp dụng Brand Voice và lịch đăng.",
                        'params' => $intent->parameters,
                        'status' => 'pending',
                        'estimated_credits' => $count * 18,
                        'requires_approval' => true,
                    ],
                ];
                break;

            case UserIntentDTO::INTENT_PUBLISH_WORDPRESS:
                $title = 'Kế hoạch xuất bản bài viết lên WordPress';
                $summary = 'Đẩy bài viết hoàn chỉnh và ảnh đính kèm lên website WordPress mục tiêu.';
                $requiresApproval = true; // Bắt buộc Human-in-the-loop

                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'wordpress_publish',
                        'action' => 'push_to_wordpress',
                        'description' => 'Gửi dữ liệu bài viết sang WordPress REST API sau khi người dùng phê duyệt.',
                        'params' => $intent->parameters,
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => true,
                    ],
                ];
                break;

            case 'content_refresh':
                $articleId = (int) $intent->getParameter('article_id', 0);
                $title = "Kế hoạch làm mới bài viết cũ #{$articleId} (AI Content Refresh)";
                $summary = 'Thu thập từ khóa GSC, đề xuất tiêu đề, bổ sung đề mục H2/H3 và tính toán điểm SEO trước/sau.';
                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'content_refresh',
                        'action' => 'plan',
                        'description' => "Phân tích Search Intent còn thiếu và sinh đề xuất làm mới cho bài viết #{$articleId}.",
                        'params' => ['article_id' => $articleId],
                        'status' => 'pending',
                        'estimated_credits' => 2,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case 'gsc_query':
            case 'gsc_performance':
            case 'gsc_analytics':
                $title = 'Kế hoạch tra cứu hiệu suất tìm kiếm Google Search Console';
                $summary = 'Truy xuất số liệu Clicks, Impressions, CTR và Top Queries từ Search Console.';
                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'gsc_query',
                        'action' => (string) $intent->getParameter('action', $intent->getParameter('query_type', 'overview')),
                        'description' => 'Tra cứu dữ liệu Search Console cho website mục tiêu.',
                        'params' => $intent->parameters,
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case 'ga4_query':
            case 'ga4_traffic':
            case 'ga4_analytics':
                $title = 'Kế hoạch tra cứu lưu lượng truy cập Google Analytics 4';
                $summary = 'Truy xuất số liệu Sessions, Active Users và Top Landing Pages từ GA4.';
                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'ga4_query',
                        'action' => (string) $intent->getParameter('action', $intent->getParameter('query_type', 'overview')),
                        'description' => 'Tra cứu dữ liệu truy cập và tương tác GA4.',
                        'params' => $intent->parameters,
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case 'rank_tracker':
                $title = 'Kế hoạch kiểm tra thứ hạng từ khóa (Rank Tracker)';
                $summary = 'Tra cứu vị trí xếp hạng Top 100 và lịch sử biến động thứ hạng.';
                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'rank_tracker',
                        'action' => (string) $intent->getParameter('action', 'summary'),
                        'description' => 'Kiểm tra thứ hạng từ khóa mục tiêu của website.',
                        'params' => $intent->parameters,
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => false,
                    ],
                ];
                break;

            case 'seo_intelligence':
                $title = 'Kế hoạch phát hiện cơ hội & cảnh báo SEO Intelligence';
                $summary = 'Quét dữ liệu heuristics để phát hiện các bài viết tụt traffic hoặc có cơ hội tăng trưởng.';
                $steps = [
                    [
                        'step_number' => 1,
                        'tool' => 'seo_intelligence',
                        'action' => (string) $intent->getParameter('action', 'summary'),
                        'description' => 'Quét cảnh báo bất thường và chấm điểm tiềm năng Opportunity Score.',
                        'params' => $intent->parameters,
                        'status' => 'pending',
                        'estimated_credits' => 0,
                        'requires_approval' => false,
                    ],
                ];
                break;

            default:
                $rawLower = mb_strtolower($intent->rawQuery ?: (string) $intent->getParameter('query', ''), 'UTF-8');
                if (str_contains($rawLower, 'clicks') || str_contains($rawLower, 'search console') || str_contains($rawLower, 'gsc') || str_contains($rawLower, 'lượt click') || str_contains($rawLower, 'lượt hiển thị') || str_contains($rawLower, 'impressions')) {
                    $title = 'Tra cứu dữ liệu Google Search Console';
                    $summary = 'Truy xuất các chỉ số Clicks, Impressions, CTR và Top Queries từ Search Console.';
                    $steps = [
                        [
                            'step_number' => 1,
                            'tool' => 'gsc_query',
                            'action' => str_contains($rawLower, 'từ khóa') || str_contains($rawLower, 'query') ? 'top_queries' : 'overview',
                            'description' => 'Tra cứu số liệu Search Console cho website mục tiêu.',
                            'params' => ['website_id' => $targetWebsite?->id],
                            'status' => 'pending',
                            'estimated_credits' => 0,
                            'requires_approval' => false,
                        ],
                    ];
                } elseif (str_contains($rawLower, 'ga4') || str_contains($rawLower, 'analytics') || str_contains($rawLower, 'truy cập') || str_contains($rawLower, 'sessions') || str_contains($rawLower, 'landing page')) {
                    $title = 'Tra cứu lưu lượng truy cập Google Analytics 4';
                    $summary = 'Truy xuất số liệu Sessions, Active Users và Top Landing Pages từ GA4.';
                    $steps = [
                        [
                            'step_number' => 1,
                            'tool' => 'ga4_query',
                            'action' => str_contains($rawLower, 'landing page') || str_contains($rawLower, 'trang') ? 'top_landing_pages' : 'overview',
                            'description' => 'Tra cứu lượt truy cập và người dùng GA4.',
                            'params' => ['website_id' => $targetWebsite?->id],
                            'status' => 'pending',
                            'estimated_credits' => 0,
                            'requires_approval' => false,
                        ],
                    ];
                } elseif (str_contains($rawLower, 'top mấy') || str_contains($rawLower, 'thứ hạng') || str_contains($rawLower, 'xếp hạng') || str_contains($rawLower, 'ranking') || str_contains($rawLower, 'rank')) {
                    $title = 'Kiểm tra thứ hạng từ khóa mục tiêu';
                    $summary = 'Tra cứu vị trí xếp hạng Top 100 và lịch sử biến động từ khóa.';
                    $steps = [
                        [
                            'step_number' => 1,
                            'tool' => 'rank_tracker',
                            'action' => 'summary',
                            'description' => 'Kiểm tra biến động thứ hạng từ khóa của website.',
                            'params' => ['website_id' => $targetWebsite?->id],
                            'status' => 'pending',
                            'estimated_credits' => 0,
                            'requires_approval' => false,
                        ],
                    ];
                } elseif (str_contains($rawLower, 'tụt traffic') || str_contains($rawLower, 'bất thường') || str_contains($rawLower, 'cảnh báo') || str_contains($rawLower, 'cơ hội') || str_contains($rawLower, 'anomaly') || str_contains($rawLower, 'decay')) {
                    $title = 'Phân tích cơ hội & bất thường SEO Intelligence';
                    $summary = 'Quét và phát hiện các bài viết tụt hạng, sụt giảm lưu lượng hoặc có cơ hội bứt phá.';
                    $steps = [
                        [
                            'step_number' => 1,
                            'tool' => 'seo_intelligence',
                            'action' => 'summary',
                            'description' => 'Quét toàn diện 6 loại bất thường và chấm điểm tiềm năng.',
                            'params' => ['website_id' => $targetWebsite?->id],
                            'status' => 'pending',
                            'estimated_credits' => 0,
                            'requires_approval' => false,
                        ],
                    ];
                } elseif (str_contains($rawLower, 'tối ưu lại bài') || str_contains($rawLower, 'làm mới bài') || str_contains($rawLower, 'refresh') || str_contains($rawLower, 'tối ưu bài')) {
                    $title = 'Kế hoạch làm mới & tối ưu nội dung (AI Content Refresh)';
                    $summary = 'Bổ sung Search Intent mới từ GSC, nâng điểm SEO và tạo bản Diff Preview.';
                    $articleId = (int) $intent->getParameter('article_id', 0);
                    $steps = [
                        [
                            'step_number' => 1,
                            'tool' => 'content_refresh',
                            'action' => 'plan',
                            'description' => 'Tự động tạo kế hoạch làm mới nội dung bài viết và hiển thị so sánh Diff.',
                            'params' => ['article_id' => $articleId],
                            'status' => 'pending',
                            'estimated_credits' => 2,
                            'requires_approval' => false,
                        ],
                    ];
                } else {
                    $title = 'Trao đổi chiến lược SEO';
                    $summary = 'Hỏi đáp kiến thức SEO hoặc giải thích kết quả.';
                    $steps = [];
                }
                break;
        }

        $plan = new ExecutionPlanDTO(
            id: $planId,
            intent: $intent->intent,
            title: $title,
            summary: $summary,
            steps: $steps,
            requiresApproval: $requiresApproval,
            totalEstimatedCredits: 0,
            canExecuteImmediately: ! $requiresApproval,
            clarificationNeeded: false,
            clarificationMessage: null,
            contextMetadata: [
                'website_id' => $targetWebsite?->id,
                'website_name' => $targetWebsite?->name,
                'parameters' => $intent->parameters,
            ]
        );

        $plan->recalculateTotalCredits();
        $plan->primaryTool = $steps[0]['tool'] ?? null;
        $plan->requiresHumanConfirmation = $requiresApproval;

        return $plan;
    }

    /**
     * Xử lý luồng yêu cầu của người dùng trong phiên hội thoại Conversation.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     status: string,
     *     intent: UserIntentDTO,
     *     plan: ExecutionPlanDTO|null,
     *     response_message: string,
     *     requires_approval: bool,
     *     system_prompt: string,
     *     context: array<string, mixed>
     * }
     */
    public function process(string $userPrompt, Conversation $conversation, array $options = []): array
    {
        // 1. Nạp Website và Brand Knowledge Base gắn với Workspace của Conversation
        /** @var Website|null $website */
        $website = $conversation->website ?: Website::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->find($conversation->website_id);

        $brandKnowledge = null;
        if ($website) {
            $brandKnowledge = $this->loadBrandKnowledge($website, $conversation->workspace_id);
        }

        // 2. Xây dựng System Prompt với đầy đủ Tools và Brand Context
        $systemPrompt = $this->buildSystemPrompt($website, $brandKnowledge);

        // 3. Phân tích ý định người dùng (User Intent)
        $context = array_merge([
            'workspace_id' => $conversation->workspace_id,
            'website_id' => $conversation->website_id,
            'user_id' => $conversation->user_id,
            'brand' => $brandKnowledge?->brand_name,
        ], $options);

        $intent = $this->intentParser->parse($userPrompt, $context);

        // 4. Kiểm tra trường hợp mơ hồ / thiếu thông tin (P2.5.3.3)
        if ($intent->needsClarification) {
            $clarificationMsg = $intent->clarificationQuestion
                ?: 'Bạn có thể cung cấp thêm chi tiết để mình hỗ trợ chuẩn xác nhất được không?';

            $plan = $this->generateExecutionPlan($intent, $website);

            return [
                'status' => 'clarification_needed',
                'intent' => $intent,
                'plan' => $plan,
                'response_message' => $clarificationMsg,
                'requires_approval' => false,
                'system_prompt' => $systemPrompt,
                'context' => $context,
            ];
        }

        // 5. Nếu đã đủ thông tin, tạo Execution Plan hoàn chỉnh
        $plan = $this->generateExecutionPlan($intent, $website);

        // 6. Xây dựng thông điệp phản hồi thông minh cho người dùng
        $responseMessage = $this->generateOrchestratorResponseMessage($intent, $plan, $website);

        return [
            'status' => $plan->requiresApproval ? 'approval_required' : 'plan_ready',
            'intent' => $intent,
            'plan' => $plan,
            'response_message' => $responseMessage,
            'requires_approval' => $plan->requiresApproval,
            'system_prompt' => $systemPrompt,
            'context' => $context,
        ];
    }

    /**
     * Trình bày thông điệp phản hồi chuyên nghiệp của AI Orchestrator gửi người dùng.
     */
    protected function generateOrchestratorResponseMessage(
        UserIntentDTO $intent,
        ExecutionPlanDTO $plan,
        ?Website $website = null
    ): string {
        $lines = [];
        $lines[] = "Dưới đây là kế hoạch thực thi chi tiết cho yêu cầu của bạn: **{$plan->title}**\n";

        if (! empty($plan->steps)) {
            $lines[] = '### Các bước thực thi dự kiến:';
            foreach ($plan->steps as $s) {
                $lines[] = sprintf('%d. **%s** — %s *(Ước tính: %d credit)*',
                    $s['step_number'],
                    $s['action'],
                    $s['description'],
                    $s['estimated_credits']
                );
            }
            $lines[] = '';
        }

        if ($plan->requiresApproval) {
            $lines[] = sprintf('> ⚠️ **LƯU Ý BẢO VỆ DỮ LIỆU & CREDIT**: Thao tác này có tính chất nhạy cảm (ước tính tiêu thụ **%d credits**). Một Thẻ Phê Duyệt Tương Tác sẽ được hiển thị để bạn xác nhận trước khi hệ thống thực thi.', $plan->totalEstimatedCredits);
        } else {
            $lines[] = sprintf('Tổng chi phí ước tính: **%d credits**. Hệ thống đã sẵn sàng khởi động tác vụ.', $plan->totalEstimatedCredits);
        }

        return implode("\n", $lines);
    }

    /**
     * Định dạng thông tin BrandKnowledgeBase đưa vào System Prompt.
     */
    protected function formatBrandKnowledge(BrandKnowledgeBase $kb): string
    {
        $parts = [];
        $parts[] = '- Tên thương hiệu (Brand Name): '.$kb->brand_name;

        if (! empty($kb->tagline)) {
            $parts[] = '- Slogan / Tagline: '.$kb->tagline;
        }

        $parts[] = '- Giọng điệu & Văn phong (Tone of Voice): '.($kb->tone_of_voice ?: 'Chuyên gia, đáng tin cậy');

        if (! empty($kb->target_personas) && is_array($kb->target_personas)) {
            $personas = implode(', ', array_filter($kb->target_personas));
            if ($personas !== '') {
                $parts[] = '- Chân dung độc giả mục tiêu (Target Personas): '.$personas;
            }
        }

        if (! empty($kb->unique_selling_points) && is_array($kb->unique_selling_points)) {
            $usps = implode('; ', array_filter($kb->unique_selling_points));
            if ($usps !== '') {
                $parts[] = '- Điểm bán hàng độc nhất (USPs): '.$usps;
            }
        }

        if (! empty($kb->taboo_words) && is_array($kb->taboo_words)) {
            $taboos = implode(', ', array_filter($kb->taboo_words));
            if ($taboos !== '') {
                $parts[] = '- TỪ NGỮ CẤM KỴ (TABOO WORDS - TUYỆT ĐỐI KHÔNG ĐƯỢC XUẤT HIỆN TRONG BÀI): '.$taboos;
            }
        }

        if (! empty($kb->preferred_terms) && is_array($kb->preferred_terms)) {
            $terms = implode(', ', array_filter($kb->preferred_terms));
            if ($terms !== '') {
                $parts[] = '- Thuật ngữ ưu tiên sử dụng tự nhiên: '.$terms;
            }
        }

        if (! empty($kb->custom_rules)) {
            $parts[] = '- Quy tắc biên tập riêng: '.trim($kb->custom_rules);
        }

        return implode("\n", $parts);
    }
}
