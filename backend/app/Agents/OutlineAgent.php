<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;

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
            'word_count_target' => 1800,
            'competitors_summary' => $competitorsSummary,
        ], $tenantId);

        $request = new AIRequestDTO(
            systemPrompt: $promptData['system_prompt'],
            userPrompt: $promptData['user_prompt'],
            model: 'gpt-4o-mini',
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
            // Retry once
            $retryRequest = new AIRequestDTO(
                systemPrompt: $promptData['system_prompt'],
                userPrompt: $promptData['user_prompt'] . "\n\nReturn ONLY valid JSON, no markdown code blocks.",
                model: 'gpt-4o-mini',
                agentType: $this->getType(),
                tenantId: $tenantId,
                articleId: $context['article_id'] ?? null,
                keywordId: $context['keyword_id'] ?? null,
                promptVersion: $promptData['version'],
            );
            $response = $this->callAI($retryRequest);
            $content = $this->cleanJsonResponse($response->content);
            $parsed = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return AgentResultDTO::failure($this->getType(), "Failed to parse JSON outline: " . json_last_error_msg());
            }
        }

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
