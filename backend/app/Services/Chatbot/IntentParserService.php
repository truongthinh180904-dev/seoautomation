<?php

namespace App\Services\Chatbot;

use App\DTOs\AIRequestDTO;
use App\DTOs\Chatbot\UserIntentDTO;
use App\Enums\AgentType;
use App\Services\AI\AIProviderService;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntentParserService
{
    public function __construct(
        protected ?AIProviderService $aiProviderService = null
    ) {}

    /**
     * Phân tích câu nói của người dùng thành UserIntentDTO có cấu trúc.
     *
     * @param  array<string, mixed>  $context  Ngữ cảnh phiên chat (website, history, brand, v.v.)
     */
    public function parse(string $input, array $context = []): UserIntentDTO
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_GENERAL_CHAT,
                confidence: 1.0,
                parameters: [],
                needsClarification: false,
                missingFields: [],
                clarificationQuestion: null,
                suggestedActions: [
                    'Nghiên cứu từ khóa',
                    'Phân tích 3 đối thủ Google SERP',
                    'Viết bài SEO mới',
                ],
                rawQuery: $input
            );
        }

        return $this->parseFromRules($trimmed, $context);
    }

    /**
     * Phân tích ý định sử dụng hệ thống luật và trích xuất thực thể theo ngữ nghĩa SEO.
     *
     * @param  array<string, mixed>  $context
     */
    public function parseFromRules(string $input, array $context = []): UserIntentDTO
    {
        $lower = mb_strtolower($input, 'UTF-8');

        // 1. Kiểm tra Ý định: Đăng bài WordPress (Publish)
        if ($this->matchesPattern($lower, [
            'đăng bài',
            'đăng lên wordpress',
            'publish lên wordpress',
            'xuất bản lên wordpress',
            'xuất bản bài',
            'publish bài',
            'up bài lên web',
            'đăng wordpress',
        ])) {
            return $this->handlePublishWordpressIntent($input, $lower, $context);
        }

        // 2. Kiểm tra Ý định: Tạo bài hàng loạt (Bulk Generation)
        if ($this->matchesPattern($lower, [
            'hàng loạt',
            'viết hàng loạt',
            'tạo bài hàng loạt',
            'bulk generation',
            'chạy lô',
            'sản xuất hàng loạt',
            'tạo loạt bài',
            'bulk bài',
        ])) {
            return $this->handleBulkGenerationIntent($input, $lower, $context);
        }

        // 3. Kiểm tra Ý định: Chấm điểm bài viết (SEO Score / Audit)
        if ($this->matchesPattern($lower, [
            'chấm điểm seo',
            'chấm điểm bài',
            'audit seo',
            'kiểm tra seo',
            'đánh giá bài viết',
            'seo score',
            'soi điểm seo',
            'audit bài',
            'chấm điểm',
        ])) {
            return $this->handleSeoScoreIntent($input, $lower, $context);
        }

        // 4. Kiểm tra Ý định: Viết lại / Biên tập bài viết (Rewrite / Polish)
        if ($this->matchesPattern($lower, [
            'viết lại',
            'biên tập lại',
            'rewrite',
            'tối ưu lại đoạn',
            'mở rộng đoạn',
            'sửa đoạn văn',
            'sửa đoạn',
            'làm mới đoạn',
            'viết lại đoạn',
            'paraphrase',
        ])) {
            return $this->handleArticleRewriteIntent($input, $lower, $context);
        }

        // 5. Kiểm tra Ý định: Cụm chủ đề (Topic Cluster)
        if ($this->matchesPattern($lower, [
            'topic cluster',
            'lập cluster',
            'gom nhóm từ khóa',
            'cụm chủ đề',
            'gom cụm',
            'xây dựng topic cluster',
            'pillar page',
            'cụm bài viết',
        ])) {
            return $this->handleTopicClusterIntent($input, $lower, $context);
        }

        // 6. Kiểm tra Ý định: Phân tích Đối thủ & SERP
        if ($this->matchesPattern($lower, [
            'phân tích đối thủ',
            'cào đối thủ',
            'so sánh đối thủ',
            'đối thủ cạnh tranh',
            'competitor',
            'top 3 đối thủ',
            'top 10 đối thủ',
            'soi đối thủ',
            'quét đối thủ',
            'serp',
        ]) || str_contains($lower, 'đối thủ')) {
            return $this->handleCompetitorAnalysisIntent($input, $lower, $context);
        }

        // 7. Kiểm tra Ý định: Nghiên cứu từ khóa (Keyword Research)
        if (! $this->matchesPattern($lower, ['viết bài', 'tạo bài', 'soạn bài', 'viết một bài', 'viết 1 bài', 'lên bài'])
            && ($this->matchesPattern($lower, [
                'nghiên cứu từ khóa',
                'tìm từ khóa',
                'gợi ý từ khóa',
                'keyword research',
                'phân tích từ khóa',
                'tra cứu từ khóa',
                'tìm kiếm từ khóa',
                'bộ từ khóa',
                'danh sách từ khóa',
            ]) || preg_match('/(tìm|kiếm|gợi ý|nghiên cứu|tra cứu|lấy|liệt kê).*?từ khóa/ui', $lower))) {
            return $this->handleKeywordResearchIntent($input, $lower, $context);
        }

        // 8. Kiểm tra Ý định: Viết bài SEO (Article Write)
        if ($this->matchesPattern($lower, [
            'viết bài',
            'tạo bài viết',
            'viết bài seo',
            'soạn bài',
            'generate article',
            'viết content',
            'lên bài',
            'viết cho tôi một bài',
            'sản xuất bài',
            'viết 1 bài',
            'viết một bài',
            'tạo bài mới',
        ])) {
            return $this->handleArticleWriteIntent($input, $lower, $context);
        }

        // Fallback: Trò chuyện chung (General Chat)
        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_GENERAL_CHAT,
            confidence: 0.85,
            parameters: [
                'query' => $input,
            ],
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Nghiên cứu từ khóa',
                'Phân tích Top 3 đối thủ',
                'Viết bài SEO mới',
                'Chấm điểm SEO bài viết',
            ],
            rawQuery: $input
        );
    }

    /**
     * Tùy chọn gọi mô hình ngôn ngữ lớn (LLM) để phân tích câu lệnh phức tạp.
     *
     * @param  array<string, mixed>  $context
     */
    public function parseWithLLM(string $input, array $context = []): UserIntentDTO
    {
        if (! $this->aiProviderService) {
            return $this->parseFromRules($input, $context);
        }

        try {
            $systemPrompt = <<<'SYS'
Bạn là bộ phân tích ý định (Intent Parser) cho hệ thống SaaS Aura SEO Assistant.
Nhiệm vụ của bạn là đọc câu yêu cầu của người dùng và trả về JSON thuần túy (không markdown) với cấu trúc sau:
{
  "intent": "keyword_research" | "competitor_analysis" | "topic_cluster" | "article_write" | "article_rewrite" | "seo_score" | "bulk_generation" | "publish_wordpress" | "general_chat",
  "confidence": float (0.0 - 1.0),
  "parameters": {
     "keyword": string|null,
     "seed_keyword": string|null,
     "domain": string|null,
     "word_count": int|null,
     "count": int|null,
     "article_id": int|null,
     "target_section": string|null
  },
  "needs_clarification": boolean,
  "missing_fields": list<string>,
  "clarification_question": string|null,
  "suggested_actions": list<string>
}
Nếu câu lệnh quá mơ hồ (ví dụ: "Viết bài đi", "Cào đối thủ"), đặt needs_clarification = true kèm clarification_question lịch sự.
SYS;

            $requestDto = new AIRequestDTO(
                systemPrompt: $systemPrompt,
                userPrompt: "Câu yêu cầu: \"{$input}\"",
                agentType: AgentType::SERP_RESEARCH,
                tenantId: (int) ($context['tenant_id'] ?? 0),
                maxTokens: 500,
                temperature: 0.1
            );

            $response = $this->aiProviderService->complete($requestDto);
            $parsedJson = json_decode($response->content, true);

            if (is_array($parsedJson) && isset($parsedJson['intent'])) {
                return new UserIntentDTO(
                    intent: (string) $parsedJson['intent'],
                    confidence: (float) ($parsedJson['confidence'] ?? 0.9),
                    parameters: (array) ($parsedJson['parameters'] ?? []),
                    needsClarification: (bool) ($parsedJson['needs_clarification'] ?? false),
                    missingFields: (array) ($parsedJson['missing_fields'] ?? []),
                    clarificationQuestion: $parsedJson['clarification_question'] ?? null,
                    suggestedActions: (array) ($parsedJson['suggested_actions'] ?? []),
                    rawQuery: $input
                );
            }
        } catch (Throwable $e) {
            Log::warning('IntentParserService::parseWithLLM fallback to rules: '.$e->getMessage());
        }

        return $this->parseFromRules($input, $context);
    }

    /**
     * Xử lý ý định Viết bài SEO (Article Write).
     *
     * @param  array<string, mixed>  $context
     */
    protected function handleArticleWriteIntent(string $input, string $lower, array $context): UserIntentDTO
    {
        $keyword = $this->extractQuotedString($input);
        if (! $keyword) {
            $keyword = $this->extractAfterPatterns($input, [
                'về từ khóa',
                'theo từ khóa',
                'với từ khóa',
                'cho từ khóa',
                'chủ đề',
                'về',
            ]);
        }

        $wordCount = $this->extractWordCount($input);
        $tone = $this->extractTone($lower);

        // Trường hợp mơ hồ hoặc thiếu thông tin: người dùng chỉ nói "Viết bài đi", "Viết bài", v.v.
        $isVague = empty($keyword) || mb_strlen($keyword, 'UTF-8') < 3;
        $onlyAction = in_array(trim($lower), [
            'viết bài đi',
            'viết bài',
            'viết bài seo',
            'tạo bài mới',
            'viết cho tôi một bài',
            'tạo bài viết',
            'lên bài đi',
            'soạn bài đi',
        ], true);

        if ($isVague || $onlyAction) {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_ARTICLE_WRITE,
                confidence: 0.95,
                parameters: array_filter([
                    'word_count' => $wordCount,
                    'tone' => $tone,
                ]),
                needsClarification: true,
                missingFields: ['keyword'],
                clarificationQuestion: 'Bạn muốn viết về từ khóa nào? Đã có dàn ý chưa hay để mình lập dàn ý giúp bạn?',
                suggestedActions: [
                    'Viết bài về: Giá bọc răng sứ thẩm mỹ',
                    'Viết bài về: Cấy ghép Implant có đau không',
                    'Lập dàn ý trước cho mình',
                ],
                rawQuery: $input
            );
        }

        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_ARTICLE_WRITE,
            confidence: 0.95,
            parameters: array_filter([
                'keyword' => $keyword,
                'word_count' => $wordCount ?: 2000,
                'tone' => $tone ?: 'chuyên gia',
            ]),
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Bắt đầu phân tích đối thủ & lập dàn ý',
                'Chỉ định số từ khác',
            ],
            rawQuery: $input
        );
    }

    /**
     * Xử lý ý định Phân tích đối thủ & SERP.
     *
     * @param  array<string, mixed>  $context
     */
    protected function handleCompetitorAnalysisIntent(string $input, string $lower, array $context): UserIntentDTO
    {
        $domain = $this->extractDomain($input);
        $keyword = $this->extractQuotedString($input);

        if (! $keyword) {
            $keyword = $this->extractAfterPatterns($input, [
                'cho từ khóa',
                'theo từ khóa',
                'với từ khóa',
                'của từ khóa',
                'về từ khóa',
                'về',
            ]);
        }

        $isVague = empty($domain) && empty($keyword);
        $onlyAction = in_array(trim($lower), [
            'phân tích đối thủ',
            'phân tích đối thủ đi',
            'cào đối thủ',
            'cào đối thủ đi',
            'soi đối thủ',
            'quét đối thủ',
            'competitor analysis',
        ], true);

        if ($isVague || $onlyAction) {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_COMPETITOR_ANALYSIS,
                confidence: 0.92,
                parameters: [],
                needsClarification: true,
                missingFields: ['keyword_or_domain'],
                clarificationQuestion: 'Bạn muốn phân tích đối thủ cho từ khóa nào hoặc domain/URL đối thủ cụ thể nào?',
                suggestedActions: [
                    'Phân tích Top 3 từ khóa: nha khoa thẩm mỹ',
                    'Cào đối thủ domain nhakhoasmile.vn',
                ],
                rawQuery: $input
            );
        }

        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_COMPETITOR_ANALYSIS,
            confidence: 0.95,
            parameters: array_filter([
                'keyword' => $keyword,
                'target_domain' => $domain,
                'focus' => 'top_3',
            ]),
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Lấy đối thủ #1 làm mỏ neo chính',
                'Xem bảng so sánh Headings & Entities',
            ],
            rawQuery: $input
        );
    }

    /**
     * Xử lý ý định Nghiên cứu từ khóa.
     *
     * @param  array<string, mixed>  $context
     */
    protected function handleKeywordResearchIntent(string $input, string $lower, array $context): UserIntentDTO
    {
        $count = $this->extractCount($lower, 'từ khóa');
        $seedKeyword = $this->extractQuotedString($input);

        if (! $seedKeyword) {
            $seedKeyword = $this->extractAfterPatterns($input, [
                'về dịch vụ',
                'về chủ đề',
                'về ngành',
                'cho dịch vụ',
                'về',
                'cho',
            ]);
        }

        $isVague = empty($seedKeyword) || mb_strlen($seedKeyword, 'UTF-8') < 3;
        $onlyAction = in_array(trim($lower), [
            'nghiên cứu từ khóa',
            'nghiên cứu từ khóa đi',
            'tìm từ khóa',
            'tìm từ khóa cho tôi',
            'keyword research',
            'gợi ý từ khóa',
        ], true);

        if ($isVague || $onlyAction) {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_KEYWORD_RESEARCH,
                confidence: 0.92,
                parameters: array_filter(['count' => $count]),
                needsClarification: true,
                missingFields: ['seed_keyword'],
                clarificationQuestion: 'Bạn muốn nghiên cứu từ khóa xoay quanh chủ đề hoặc lĩnh vực nào? Vui lòng cung cấp từ khóa hạt giống (seed keyword) nhé.',
                suggestedActions: [
                    'Nghiên cứu từ khóa: Bọc răng sứ',
                    'Tìm 20 từ khóa về thẩm mỹ viện',
                ],
                rawQuery: $input
            );
        }

        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_KEYWORD_RESEARCH,
            confidence: 0.95,
            parameters: array_filter([
                'seed_keyword' => $seedKeyword,
                'count' => $count ?: 20,
            ]),
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Phân tích Search Intent & Gom cụm',
                'Xuất bảng từ khóa',
            ],
            rawQuery: $input
        );
    }

    /**
     * Xử lý ý định Cụm chủ đề (Topic Cluster).
     *
     * @param  array<string, mixed>  $context
     */
    protected function handleTopicClusterIntent(string $input, string $lower, array $context): UserIntentDTO
    {
        $pillar = $this->extractQuotedString($input);
        if (! $pillar) {
            $pillar = $this->extractAfterPatterns($input, [
                'cho từ khóa',
                'cho chủ đề',
                'về chủ đề',
                'cho website',
                'về',
                'cho',
            ]);
        }

        $isVague = empty($pillar) || mb_strlen($pillar, 'UTF-8') < 3;
        $onlyAction = in_array(trim($lower), [
            'lập cluster',
            'lập topic cluster',
            'topic cluster',
            'xây dựng topic cluster',
            'gom nhóm từ khóa',
        ], true);

        if ($isVague || $onlyAction) {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_TOPIC_CLUSTER,
                confidence: 0.90,
                parameters: [],
                needsClarification: true,
                missingFields: ['pillar_topic'],
                clarificationQuestion: 'Bạn muốn lập cụm chủ đề (Topic Cluster) cho từ khóa trụ cột nào? Đã có danh sách từ khóa phụ chưa hay để mình gợi ý giúp bạn?',
                suggestedActions: [
                    'Tạo cluster cho pillar: Trồng răng Implant',
                    'Lập kế hoạch Topic Cluster 3 tháng cho website',
                ],
                rawQuery: $input
            );
        }

        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_TOPIC_CLUSTER,
            confidence: 0.95,
            parameters: [
                'pillar_keyword' => $pillar,
            ],
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Xem sơ đồ phân nhánh cụm chủ đề',
                'Tạo bài viết cho các từ khóa vệ tinh',
            ],
            rawQuery: $input
        );
    }

    /**
     * Xử lý ý định Viết lại / Biên tập bài viết (Rewrite).
     *
     * @param  array<string, mixed>  $context
     */
    protected function handleArticleRewriteIntent(string $input, string $lower, array $context): UserIntentDTO
    {
        $section = null;
        if (str_contains($lower, 'mở đầu') || str_contains($lower, 'intro')) {
            $section = 'mở đầu';
        } elseif (str_contains($lower, 'kết bài') || str_contains($lower, 'conclusion')) {
            $section = 'kết bài';
        } elseif (str_contains($lower, 'thân bài') || str_contains($lower, 'body')) {
            $section = 'thân bài';
        }

        $quotedText = $this->extractQuotedString($input);
        $hasText = ! empty($quotedText) || mb_strlen($input, 'UTF-8') > 50;

        $onlyAction = in_array(trim($lower), [
            'viết lại',
            'viết lại đi',
            'sửa đoạn này',
            'rewrite',
            'biên tập lại',
        ], true);

        if (! $hasText && ! $section && $onlyAction) {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_ARTICLE_REWRITE,
                confidence: 0.90,
                parameters: [],
                needsClarification: true,
                missingFields: ['text_to_rewrite'],
                clarificationQuestion: 'Bạn muốn viết lại hoặc tối ưu đoạn văn bản nào? Hãy dán đoạn văn hoặc chỉ định phần cần sửa đổi nhé.',
                suggestedActions: [
                    'Viết lại đoạn mở đầu hấp dẫn hơn',
                    'Tối ưu đoạn văn chuẩn phong cách Brand Voice',
                ],
                rawQuery: $input
            );
        }

        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_ARTICLE_REWRITE,
            confidence: 0.95,
            parameters: array_filter([
                'target_section' => $section,
                'text_to_rewrite' => $quotedText,
                'instruction' => $input,
            ]),
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Viết lại với văn phong hấp dẫn hơn',
                'Mở rộng nội dung chi tiết',
            ],
            rawQuery: $input
        );
    }

    /**
     * Xử lý ý định Chấm điểm SEO (SEO Score).
     *
     * @param  array<string, mixed>  $context
     */
    protected function handleSeoScoreIntent(string $input, string $lower, array $context): UserIntentDTO
    {
        $articleId = $this->extractArticleId($input, $context);
        $quotedText = $this->extractQuotedString($input);

        $onlyAction = in_array(trim($lower), [
            'chấm điểm seo',
            'chấm điểm bài',
            'audit seo',
            'kiểm tra seo',
            'chấm điểm',
            'soi điểm seo',
        ], true);

        if (! $articleId && empty($quotedText) && $onlyAction) {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_SEO_SCORE,
                confidence: 0.90,
                parameters: [],
                needsClarification: true,
                missingFields: ['article_id_or_content'],
                clarificationQuestion: 'Bạn muốn chấm điểm SEO cho bài viết nào? Vui lòng cung cấp nội dung hoặc chọn bài viết nháp cần chấm điểm.',
                suggestedActions: [
                    'Chấm điểm bài viết gần nhất',
                    'Dán nội dung trực tiếp vào khung chat',
                ],
                rawQuery: $input
            );
        }

        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_SEO_SCORE,
            confidence: 0.95,
            parameters: array_filter([
                'article_id' => $articleId,
                'content' => $quotedText,
            ]),
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Xem danh sách gợi ý cải thiện',
                'Tự động sửa lỗi SEO ngay',
            ],
            rawQuery: $input
        );
    }

    /**
     * Xử lý ý định Tạo bài hàng loạt (Bulk Generation).
     *
     * @param  array<string, mixed>  $context
     */
    protected function handleBulkGenerationIntent(string $input, string $lower, array $context): UserIntentDTO
    {
        $count = $this->extractCount($lower, 'bài')
            ?: $this->extractCount($lower, 'từ khóa')
            ?: $this->extractCount($lower, 'bài viết');

        if (! $count && preg_match('/\b(?:lô|loạt)\s+(\d+)\b/ui', $lower, $matches)) {
            $count = (int) $matches[1];
        }

        $onlyAction = in_array(trim($lower), [
            'viết hàng loạt',
            'tạo bài hàng loạt',
            'bulk',
            'chạy lô',
            'sản xuất hàng loạt',
        ], true);

        if ($onlyAction || (! $count && ! str_contains($input, ','))) {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_BULK_GENERATION,
                confidence: 0.92,
                parameters: array_filter(['count' => $count]),
                needsClarification: true,
                missingFields: ['keywords'],
                clarificationQuestion: 'Bạn muốn tạo bài viết hàng loạt theo danh sách từ khóa nào? Vui lòng cung cấp danh sách từ khóa hoặc tải file lên nhé.',
                suggestedActions: [
                    'Tạo loạt 10 bài theo danh sách từ khóa',
                    'Mở trình nhập danh sách từ khóa',
                ],
                rawQuery: $input
            );
        }

        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_BULK_GENERATION,
            confidence: 0.95,
            parameters: array_filter([
                'count' => $count ?: 10,
                'raw_keywords' => $input,
            ]),
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Xem bảng chi phí credit ước tính',
                'Cấu hình lịch tự động xuất bản',
            ],
            rawQuery: $input
        );
    }

    /**
     * Xử lý ý định Xuất bản WordPress (Publish).
     *
     * @param  array<string, mixed>  $context
     */
    protected function handlePublishWordpressIntent(string $input, string $lower, array $context): UserIntentDTO
    {
        $articleId = $this->extractArticleId($input, $context);
        $onlyAction = in_array(trim($lower), [
            'đăng bài',
            'đăng bài lên wordpress',
            'publish bài',
            'xuất bản bài',
            'đăng lên web',
            'publish',
        ], true);

        if (! $articleId && $onlyAction) {
            return new UserIntentDTO(
                intent: UserIntentDTO::INTENT_PUBLISH_WORDPRESS,
                confidence: 0.92,
                parameters: [],
                needsClarification: true,
                missingFields: ['article_id'],
                clarificationQuestion: 'Bạn muốn đăng bài viết nào lên WordPress? Vui lòng cung cấp tiêu đề hoặc chọn bài viết cần đăng.',
                suggestedActions: [
                    'Đăng bài viết vừa tạo',
                    'Chọn từ danh sách bài viết đã duyệt',
                ],
                rawQuery: $input
            );
        }

        return new UserIntentDTO(
            intent: UserIntentDTO::INTENT_PUBLISH_WORDPRESS,
            confidence: 0.95,
            parameters: array_filter([
                'article_id' => $articleId,
                'target_website' => $context['website_id'] ?? null,
            ]),
            needsClarification: false,
            missingFields: [],
            clarificationQuestion: null,
            suggestedActions: [
                'Tạo thẻ xác nhận phê duyệt xuất bản',
                'Đăng dưới dạng bản nháp (Draft)',
            ],
            rawQuery: $input
        );
    }

    // ==========================================
    // CÁC HÀM TIỆN ÍCH TRÍCH XUẤT THỰC THỂ
    // ==========================================

    protected function matchesPattern(string $haystack, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($haystack, mb_strtolower($pattern, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    protected function extractQuotedString(string $input): ?string
    {
        // Trích chuỗi trong dấu nháy kép hoặc đơn: "từ khóa" hoặc 'từ khóa'
        if (preg_match('/["\']([^"\']+)["\']/u', $input, $matches)) {
            $trimmed = trim($matches[1]);
            if (! empty($trimmed)) {
                return $trimmed;
            }
        }

        return null;
    }

    protected function extractAfterPatterns(string $input, array $prefixes): ?string
    {
        foreach ($prefixes as $prefix) {
            $pattern = '/(*UCP)(?:^|\b|\s)'.preg_quote($prefix, '/').'\s+([^,.;!?\n]+)/ui';
            if (preg_match($pattern, $input, $matches)) {
                $candidate = trim($matches[1]);
                // Bỏ qua các từ phụ thuộc ở cuối câu
                $candidate = preg_replace('/\s+(và|theo|với|bằng|dùng|chuẩn|văn phong)\s+.*$/ui', '', $candidate);
                $candidate = trim((string) $candidate);
                if (! empty($candidate) && mb_strlen($candidate, 'UTF-8') >= 2) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    protected function extractWordCount(string $input): ?int
    {
        // Tìm dạng 2.500 từ, 2500 từ, 3000 words
        if (preg_match('/(*UCP)\b([0-9]{1,2}[.,][0-9]{3}|[0-9]{3,5})\s*(từ|words?)\b/ui', $input, $matches)
            || preg_match('/([0-9]{1,2}[.,][0-9]{3}|[0-9]{3,5})\s*(?:từ|words?)(?:\s|$|[.,;!?:]|\b)/ui', $input, $matches)) {
            $cleaned = str_replace(['.', ','], '', $matches[1]);

            return (int) $cleaned;
        }

        return null;
    }

    protected function extractCount(string $lower, string $unit): ?int
    {
        $quoted = preg_quote($unit, '/');
        if (preg_match('/(*UCP)\b(\d+)\s+'.$quoted.'\b/ui', $lower, $matches)
            || preg_match('/(?:^|\s)(\d+)\s+'.$quoted.'(?:\s|$|[.,;!?:]|\b)/ui', $lower, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    protected function extractDomain(string $input): ?string
    {
        // Tìm pattern domain như nhakhoasmile.vn, example.com
        if (preg_match('/\b([a-zA-Z0-9][-a-zA-Z0-9]*\.(?:com|vn|net|org|edu|info|io|ai|co)(?:\.[a-zA-Z]{2})?)\b/ui', $input, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return null;
    }

    protected function extractTone(string $lower): ?string
    {
        if (str_contains($lower, 'chuyên gia')) {
            return 'chuyên gia';
        }
        if (str_contains($lower, 'thân thiện')) {
            return 'thân thiện';
        }
        if (str_contains($lower, 'hài hước')) {
            return 'hài hước';
        }
        if (str_contains($lower, 'học thuật')) {
            return 'học thuật';
        }

        return null;
    }

    protected function extractArticleId(string $input, array $context): ?int
    {
        if (! empty($context['article_id'])) {
            return (int) $context['article_id'];
        }

        if (preg_match('/(*UCP)\bbài(?:\s+viết)?\s*(?:id|số|#)?\s*(\d+)\b/ui', $input, $matches)
            || preg_match('/bài(?:\s+viết)?\s*(?:id|số|#)?\s*(\d+)/ui', $input, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
