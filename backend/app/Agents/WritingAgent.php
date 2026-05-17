<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;
use App\Exceptions\Article\DuplicateContentException;
use App\Services\Article\DuplicateDetectionService;
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
        ], $tenantId);

        $request = new AIRequestDTO(
            systemPrompt: $promptData['system_prompt'],
            userPrompt: $promptData['user_prompt'],
            model: 'gpt-4o',
            agentType: $this->getType(),
            tenantId: $tenantId,
            articleId: $context['article_id'] ?? null,
            keywordId: $context['keyword_id'] ?? null,
            promptVersion: $promptData['version'],
        );

        $response = $this->callAI($request);
        $content = $this->cleanJsonResponse($response->content);
        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return AgentResultDTO::failure($this->getType(), "Failed to parse JSON article: " . json_last_error_msg());
        }

        $title = $parsed['title'] ?? '';
        $htmlContent = $parsed['content'] ?? '';
        $excerpt = $parsed['excerpt'] ?? '';

        if (empty($title) || empty($htmlContent)) {
            return AgentResultDTO::failure($this->getType(), "Article validation failed: Missing title or content");
        }

        if (stripos($title, $keyword) === false) {
            return AgentResultDTO::failure($this->getType(), "Article validation failed: Title must contain keyword");
        }

        $wordCount = str_word_count(strip_tags($htmlContent));
        if ($wordCount <= 1200) {
            // Usually we might retry or accept with a warning, but acceptance criteria says "must be > 1200"
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

    protected function cleanJsonResponse(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```json')) {
            $content = substr($content, 7);
        } elseif (str_starts_with($content, '```')) {
            $content = substr($content, 3);
        }
        if (str_ends_with($content, '```')) {
            $content = substr($content, 0, -3);
        }
        return trim($content);
    }
}
