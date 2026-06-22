<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;
use App\Exceptions\Article\DuplicateContentException;
use App\Services\Article\DuplicateDetectionService;
use App\Services\Article\ArticleContentFormatter;
use App\Services\AI\JsonResponseParser;
use Illuminate\Support\Facades\Log;

class WritingAgent extends BaseAgent
{
    public function getType(): AgentType
    {
        return AgentType::WRITING;
    }

    protected function run(array $context): AgentResultDTO
    {
        $keyword = $context['keyword'] ?? null;
        $outline = $context['outline'] ?? [];
        $tenantId = $context['tenant_id'] ?? null;

        if (!$keyword || empty($outline) || !$tenantId) {
            throw new \InvalidArgumentException("Missing required context fields.");
        }

        $semanticKeywords = implode(', ', $context['semantic_keywords'] ?? []);
        $entities = implode(', ', $context['entities'] ?? []);
        $targetWordCount = max(1200, min(5000, (int) ($context['target_word_count'] ?? 3000)));
        $minWordCount = max(900, (int) round($targetWordCount * 0.75));
        $maxWordCount = (int) round($targetWordCount * 1.15);
        
        $faqsToAnswer = '';
        if (!empty($context['faqs']) && is_array($context['faqs'])) {
            $faqsToAnswer = json_encode($context['faqs'], JSON_UNESCAPED_UNICODE);
        }

        $promptData = $this->promptBuilderService->build($this->getType(), [
            'keyword' => $keyword,
            'outline_json' => json_encode($outline, JSON_UNESCAPED_UNICODE),
            'semantic_keywords' => $semanticKeywords,
            'entities' => $entities,
            'faqs_to_answer' => $faqsToAnswer,
            'target_word_count' => $targetWordCount,
            'brief_notes' => $context['brief_notes'] ?? '',
            'must_include_points' => implode("\n", $context['must_include_points'] ?? []),
            'avoid_topics' => implode("\n", $context['avoid_topics'] ?? []),
        ], $tenantId);

        $request = new AIRequestDTO(
            systemPrompt: $promptData['system_prompt'],
            userPrompt: $promptData['user_prompt'] . "\n\nYêu cầu định dạng bắt buộc:\n- Return ONLY valid JSON, không bọc markdown/code fence.\n- Độ dài mục tiêu: khoảng {$targetWordCount} từ, tối thiểu {$minWordCount}, tối đa {$maxWordCount}; không viết lan man vượt giới hạn.\n- content phải là HTML sạch cho WordPress, chỉ dùng <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>.\n- Không dùng <h1> trong content vì title đã được lưu riêng.\n- Không lặp lại title ở đầu content.\n- Mỗi heading có 1-3 đoạn nội dung hữu ích, tự nhiên, không keyword stuffing.\n- Ưu tiên thông tin thực tế, câu ngắn, đoạn ngắn, không lỗi font/ký tự lạ.\n- Không trả chuỗi JSON bên trong content.",
            model: '',
            agentType: $this->getType(),
            tenantId: $tenantId,
            maxTokens: 12000,
            articleId: $context['article_id'] ?? null,
            keywordId: $context['keyword_id'] ?? null,
            promptVersion: $promptData['version'],
        );

        $response = $this->callAI($request);
        $parser = app(JsonResponseParser::class);
        $parsed = $parser->parse($response->content);

        if (!$parsed) {
            Log::warning('WritingAgent JSON parse failed, falling back to raw content', [
                'keyword_id' => $context['keyword_id'] ?? null,
                'article_id' => $context['article_id'] ?? null,
                'model' => $response->model,
                'response_preview' => $parser->preview($response->content),
            ]);

            $parsed = $this->buildArticleFromRawResponse($response->content, $keyword);
        }

        $title = trim((string) ($parsed['title'] ?? ''));
        $htmlContent = app(ArticleContentFormatter::class)->normalize((string) ($parsed['content'] ?? ''), $title);
        $excerpt = trim((string) ($parsed['excerpt'] ?? ''));

        if (empty($title) || empty($htmlContent)) {
            return AgentResultDTO::failure($this->getType(), "Article validation failed: Missing title or content");
        }

        if (stripos($title, $keyword) === false) {
            $title = "{$keyword}: {$title}";
        }

        $formatter = app(ArticleContentFormatter::class);
        $wordCount = $formatter->wordCount($htmlContent);
        if ($wordCount <= 500) {
            return AgentResultDTO::failure($this->getType(), "Article validation failed: Word count too low ({$wordCount})");
        }

        // Duplicate detection — compute hash and check against existing articles
        $detector = app(DuplicateDetectionService::class);
        $contentHash = $detector->validateAndHash($htmlContent, $context['article_id'] ?? null);

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: [
                'title'                => $title,
                'content'             => $htmlContent,
                'excerpt'             => $excerpt,
                'word_count'          => $wordCount,
                'ai_provider'         => $response->provider->value,
                'ai_model'            => $response->model,
                'ai_cost_usd'         => $response->totalCostUsd(),
                'duplicate_check_hash' => $contentHash,
            ],
            tokens: $response->totalTokens,
            latency: $response->latencyMs
        );
    }

    protected function buildArticleFromRawResponse(string $content, string $keyword): array
    {
        $content = app(JsonResponseParser::class)->clean($content);
        $content = trim($content);

        if (!str_contains($content, '<')) {
            $paragraphs = array_filter(array_map('trim', preg_split('/\R{2,}/', $content) ?: []));
            $content = '<h1>' . e($keyword) . '</h1>' . implode('', array_map(
                fn (string $paragraph) => '<p>' . nl2br(e($paragraph)) . '</p>',
                $paragraphs
            ));
        }

        $title = $this->extractTitle($content) ?: $keyword;
        $excerpt = mb_substr(trim(strip_tags($content)), 0, 220);

        return [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
        ];
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return null;
    }
}
