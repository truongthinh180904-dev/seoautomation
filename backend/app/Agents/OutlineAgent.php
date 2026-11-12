<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;
use App\Services\AI\JsonResponseParser;
use Illuminate\Support\Facades\Log;

class OutlineAgent extends BaseAgent
{
    public function getType(): AgentType
    {
        return AgentType::OUTLINE;
    }

    protected function run(array $context): AgentResultDTO
    {
        $keyword = $context['keyword'] ?? null;
        $searchIntent = $context['search_intent'] ?? '';
        $targetWordCount = (int) ($context['target_word_count'] ?? 1800);
        $competitorData = $context['competitor_data'] ?? [];
        $tenantId = $context['tenant_id'] ?? null;
        $brandContext = (string) ($context['brand_context'] ?? '');
        $strategyBlock = (string) ($context['strategy_block'] ?? '');
        $strategyAnalysis = is_array($context['strategy_analysis'] ?? null) ? $context['strategy_analysis'] : [];

        if (! $keyword || ! $tenantId) {
            throw new \InvalidArgumentException('Missing required context fields.');
        }

        $competitorsSummary = '';
        foreach ($competitorData as $comp) {
            if (is_array($comp)) {
                $competitorsSummary .= 'URL: '.($comp['url'] ?? '')."\n";
                $competitorsSummary .= 'Headings: '.json_encode($comp['heading_structure'] ?? [], JSON_UNESCAPED_UNICODE)."\n\n";
            } elseif (is_object($comp) && method_exists($comp, 'toSummary')) {
                $competitorsSummary .= $comp->toSummary()."\n\n";
            }
        }

        $promptData = $this->promptBuilderService->build($this->getType(), [
            'keyword' => $keyword,
            'search_intent' => $searchIntent,
            'word_count_target' => max(800, min(5000, $targetWordCount)),
            'competitors_summary' => $competitorsSummary,
        ], $tenantId);

        $systemPrompt = $promptData['system_prompt'];
        if ($brandContext !== '') {
            $systemPrompt .= "\n\n".$brandContext;
        }

        $userPrompt = $promptData['user_prompt'];
        if ($strategyBlock !== '') {
            $userPrompt .= "\n\n".$strategyBlock;
            $userPrompt .= "\n\nYÊU CẦU DÀN Ý (STEP 5 - BUILD ARTICLE OUTLINE):"
                ."\n- Dàn ý phải có logic chặt chẽ, KHÔNG trùng lặp ý giữa các H2/H3."
                ."\n- Đáp ứng trực diện Primary Search Intent và Secondary Intents."
                ."\n- Bám sát cấu trúc của Content Type đã chọn, có chiều sâu chuyên môn phù hợp ngành nghề."
                ."\n- Không đặt các heading vô nghĩa chỉ để nhồi từ khóa."
                ."\n- Bao gồm mở bài, các phần nội dung chính (sections với heading và points cụ thể), danh sách faqs thực tế và định hướng kết luận/CTA.";
        }

        $preferredModel = (string) config('ai.providers.gemini.content_model', 'gemini-3.1-pro-preview');

        $request = new AIRequestDTO(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            model: $preferredModel,
            agentType: $this->getType(),
            tenantId: $tenantId,
            articleId: $context['article_id'] ?? null,
            keywordId: $context['keyword_id'] ?? null,
            promptVersion: $promptData['version'],
            workspaceId: $context['workspace_id'] ?? null,
        );

        $response = $this->callAI($request);
        $parser = app(JsonResponseParser::class);
        $parsed = $parser->parse($response->content);

        if (! $parsed) {
            $retryRequest = new AIRequestDTO(
                systemPrompt: $systemPrompt,
                userPrompt: $userPrompt."\n\nReturn ONLY one valid JSON object. Do not include markdown, comments, prose, or trailing commas.",
                model: $preferredModel,
                agentType: $this->getType(),
                tenantId: $tenantId,
                articleId: $context['article_id'] ?? null,
                keywordId: $context['keyword_id'] ?? null,
                promptVersion: $promptData['version'],
                workspaceId: $context['workspace_id'] ?? null,
            );
            $response = $this->callAI($retryRequest);
            $parsed = $parser->parse($response->content);

            if (! $parsed) {
                Log::warning('OutlineAgent JSON parse failed', [
                    'keyword_id' => $context['keyword_id'] ?? null,
                    'article_id' => $context['article_id'] ?? null,
                    'model' => $response->model,
                    'response_preview' => $parser->preview($response->content),
                ]);

                $fallbackOutline = $this->buildFallbackOutline($keyword, $strategyAnalysis);

                return AgentResultDTO::success(
                    agent: $this->getType(),
                    data: ['outline' => $fallbackOutline],
                    tokens: $response->totalTokens,
                    latency: $response->latencyMs
                );
            }
        }

        $parsed = $this->normalizeOutline($parsed, $keyword, $strategyAnalysis);

        if (empty($parsed['h1'])) {
            return AgentResultDTO::failure($this->getType(), 'Outline validation failed: Missing h1');
        }
        if (empty($parsed['sections']) || count($parsed['sections']) < 4) {
            return AgentResultDTO::failure($this->getType(), 'Outline validation failed: Minimum 4 sections required');
        }

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: ['outline' => $parsed],
            tokens: $response->totalTokens,
            latency: $response->latencyMs
        );
    }

    /**
     * @param  array<string, mixed>  $strategyAnalysis
     */
    protected function normalizeOutline(array $outline, string $keyword, array $strategyAnalysis = []): array
    {
        if (array_is_list($outline)) {
            $outline = ['sections' => $outline];
        }

        $sections = $outline['sections'] ?? [];

        if (is_array($sections)) {
            $outline['sections'] = array_values(array_map(function ($section) {
                if (is_string($section)) {
                    return ['heading' => $section, 'points' => []];
                }

                if (is_array($section)) {
                    return [
                        'heading' => (string) ($section['heading'] ?? $section['title'] ?? $section['h2'] ?? 'Mục nội dung'),
                        'points' => array_values((array) ($section['points'] ?? $section['bullets'] ?? $section['subheadings'] ?? [])),
                    ];
                }

                return ['heading' => 'Mục nội dung', 'points' => []];
            }, $sections));
        }

        $outline['h1'] = $outline['h1'] ?? $outline['title'] ?? $keyword;
        $outline['faqs'] = is_array($outline['faqs'] ?? null) ? $outline['faqs'] : [];

        if (! empty($strategyAnalysis)) {
            $outline['content_type'] = $strategyAnalysis['step_4_content_strategy']['content_type'] ?? ($outline['content_type'] ?? 'informational_article');
            $outline['primary_search_intent'] = $strategyAnalysis['step_2_keyword_analysis']['primary_search_intent'] ?? ($outline['primary_search_intent'] ?? 'informational');
            $outline['secondary_search_intents'] = $strategyAnalysis['step_2_keyword_analysis']['secondary_search_intents'] ?? ($outline['secondary_search_intents'] ?? []);
            $outline['strategy_analysis'] = $strategyAnalysis;
        }

        return $outline;
    }

    /**
     * @param  array<string, mixed>  $strategyAnalysis
     */
    protected function buildFallbackOutline(string $keyword, array $strategyAnalysis = []): array
    {
        $blueprint = $strategyAnalysis['step_4_content_strategy']['structure_blueprint'] ?? [];
        $sections = [];

        if (! empty($blueprint) && is_array($blueprint)) {
            foreach ($blueprint as $headingTemplate) {
                $sections[] = [
                    'heading' => $headingTemplate.': '.$keyword,
                    'points' => [
                        'Phân tích chuyên môn và các tiêu chí cốt lõi',
                        'Dữ liệu thực tế, ưu nhược điểm và điều kiện áp dụng',
                        'Lưu ý quan trọng giúp tối ưu hiệu quả và phòng tránh rủi ro',
                    ],
                ];
            }
        } else {
            $sections = [
                [
                    'heading' => "Tổng quan và bản chất cốt lõi của {$keyword}",
                    'points' => ['Nhu cầu thực tế', 'Giá trị mang lại', 'Khi nào nên áp dụng'],
                ],
                [
                    'heading' => "Các tiêu chí đánh giá và chuẩn bị cho {$keyword}",
                    'points' => ['Tiêu chuẩn chất lượng', 'Các yếu tố ảnh hưởng', 'Lưu ý quan trọng'],
                ],
                [
                    'heading' => "Quy trình thực hiện và triển khai {$keyword} hiệu quả",
                    'points' => ['Các bước tiêu chuẩn', 'Cách tối ưu kết quả', 'Sai lầm thường gặp cần tránh'],
                ],
                [
                    'heading' => 'Kinh nghiệm thực tế và giải đáp thắc mắc thường gặp',
                    'points' => ['Lời khuyên từ góc nhìn chuyên môn', 'Cách kiểm tra chất lượng', 'Tổng hợp FAQ'],
                ],
            ];
        }

        return [
            'h1' => $keyword,
            'content_type' => $strategyAnalysis['step_4_content_strategy']['content_type'] ?? 'informational_article',
            'primary_search_intent' => $strategyAnalysis['step_2_keyword_analysis']['primary_search_intent'] ?? 'informational',
            'secondary_search_intents' => $strategyAnalysis['step_2_keyword_analysis']['secondary_search_intents'] ?? [],
            'sections' => array_slice($sections, 0, 6),
            'faqs' => [
                [
                    'question' => "Cần lưu ý điều gì quan trọng nhất khi tìm hiểu về {$keyword}?",
                    'answer' => 'Cần xác định rõ nhu cầu thực tế, kiểm tra các tiêu chuẩn chuyên môn và tham khảo thông tin minh bạch trước khi quyết định.',
                ],
                [
                    'question' => "Làm thế nào để đánh giá chất lượng và chi phí của {$keyword}?",
                    'answer' => 'Nên so sánh dựa trên phạm vi hạng mục bao gồm, năng lực chuyên môn của đơn vị cung cấp và chính sách bảo hành đi kèm.',
                ],
            ],
            'strategy_analysis' => $strategyAnalysis,
        ];
    }
}
