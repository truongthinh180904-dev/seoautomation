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
        $targetWordCount = (int) ($context['target_word_count'] ?? 3000);
        $competitorData = $context['competitor_data'] ?? [];
        $tenantId = $context['tenant_id'] ?? null;
        
        if (!$keyword || !$tenantId) {
            throw new \InvalidArgumentException("Missing required context fields.");
        }

        $competitorsSummary = '';
        foreach ($competitorData as $comp) {
            if (is_array($comp)) {
                $competitorsSummary .= "URL: " . ($comp['url'] ?? '') . "\n";
                $competitorsSummary .= "Headings: " . json_encode($comp['heading_structure'] ?? [], JSON_UNESCAPED_UNICODE) . "\n\n";
            } elseif (is_object($comp) && method_exists($comp, 'toSummary')) {
                $competitorsSummary .= $comp->toSummary() . "\n\n";
            }
        }

        $promptData = $this->promptBuilderService->build($this->getType(), [
            'keyword' => $keyword,
            'search_intent' => $searchIntent,
            'word_count_target' => max(1200, min(5000, $targetWordCount)),
            'competitors_summary' => $competitorsSummary,
        ], $tenantId);

        $request = new AIRequestDTO(
            systemPrompt: $promptData['system_prompt'],
            userPrompt: $promptData['user_prompt'],
            model: '',
            agentType: $this->getType(),
            tenantId: $tenantId,
            articleId: $context['article_id'] ?? null,
            keywordId: $context['keyword_id'] ?? null,
            promptVersion: $promptData['version'],
        );

        $response = $this->callAI($request);
        $parser = app(JsonResponseParser::class);
        $parsed = $parser->parse($response->content);

        if (!$parsed) {
            $retryRequest = new AIRequestDTO(
                systemPrompt: $promptData['system_prompt'],
                userPrompt: $promptData['user_prompt'] . "\n\nReturn ONLY one valid JSON object. Do not include markdown, comments, prose, or trailing commas.",
                model: '',
                agentType: $this->getType(),
                tenantId: $tenantId,
                articleId: $context['article_id'] ?? null,
                keywordId: $context['keyword_id'] ?? null,
                promptVersion: $promptData['version'],
            );
            $response = $this->callAI($retryRequest);
            $parsed = $parser->parse($response->content);

            if (!$parsed) {
                Log::warning('OutlineAgent JSON parse failed', [
                    'keyword_id' => $context['keyword_id'] ?? null,
                    'article_id' => $context['article_id'] ?? null,
                    'model' => $response->model,
                    'response_preview' => $parser->preview($response->content),
                ]);

                $fallbackOutline = $this->buildFallbackOutline($keyword);
                return AgentResultDTO::success(
                    agent: $this->getType(),
                    data: ['outline' => $fallbackOutline],
                    tokens: $response->totalTokens,
                    latency: $response->latencyMs
                );
            }
        }

        $parsed = $this->normalizeOutline($parsed, $keyword);

        if (empty($parsed['h1'])) {
            return AgentResultDTO::failure($this->getType(), "Outline validation failed: Missing h1");
        }
        if (empty($parsed['sections']) || count($parsed['sections']) < 4) {
            return AgentResultDTO::failure($this->getType(), "Outline validation failed: Minimum 4 sections required");
        }

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: ['outline' => $parsed],
            tokens: $response->totalTokens,
            latency: $response->latencyMs
        );
    }

    protected function normalizeOutline(array $outline, string $keyword): array
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
                        'points' => array_values((array) ($section['points'] ?? $section['bullets'] ?? [])),
                    ];
                }

                return ['heading' => 'Mục nội dung', 'points' => []];
            }, $sections));
        }

        $outline['h1'] = $outline['h1'] ?? $outline['title'] ?? $keyword;
        $outline['faqs'] = is_array($outline['faqs'] ?? null) ? $outline['faqs'] : [];

        return $outline;
    }

    protected function buildFallbackOutline(string $keyword): array
    {
        return [
            'h1' => $keyword,
            'sections' => [
                [
                    'heading' => "Tổng quan về {$keyword}",
                    'points' => ['Nhu cầu tìm kiếm', 'Lợi ích chính', 'Khi nào nên áp dụng'],
                ],
                [
                    'heading' => "Chuẩn bị trước khi {$keyword}",
                    'points' => ['Thông tin cần có', 'Công cụ phù hợp', 'Lưu ý quan trọng'],
                ],
                [
                    'heading' => "Các bước thực hiện {$keyword}",
                    'points' => ['Quy trình từng bước', 'Cách tối ưu trải nghiệm', 'Lỗi thường gặp'],
                ],
                [
                    'heading' => "Mẹo tối ưu và câu hỏi thường gặp",
                    'points' => ['Mẹo tăng hiệu quả', 'Cách kiểm tra kết quả', 'FAQ'],
                ],
            ],
            'faqs' => [
                [
                    'question' => "{$keyword} có khó không?",
                    'answer' => 'Không khó nếu chuẩn bị đúng thông tin và làm theo từng bước.',
                ],
            ],
        ];
    }
}
