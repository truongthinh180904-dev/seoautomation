<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;

class ContentAnalysisAgent extends BaseAgent
{
    public function getType(): AgentType
    {
        return AgentType::CONTENT_ANALYSIS;
    }

    protected function run(array $context): AgentResultDTO
    {
        $keyword = $context['keyword'] ?? null;
        $content = $context['content'] ?? null;
        $tenantId = $context['tenant_id'] ?? null;

        if (!$keyword || !$content || !$tenantId) {
            throw new \InvalidArgumentException("Missing keyword, content, or tenant_id");
        }

        // Limit content length to save tokens (approx 15000 chars)
        $contentSubstring = mb_substr($content, 0, 15000);

        $promptData = $this->promptBuilderService->build($this->getType(), [
            'keyword' => $keyword,
            'content' => $contentSubstring
        ], $tenantId);

        $request = new AIRequestDTO(
            systemPrompt: $promptData['system_prompt'],
            userPrompt: $promptData['user_prompt'],
            model: 'gpt-4o-mini',
            agentType: $this->getType(),
            tenantId: $tenantId,
            keywordId: $context['keyword_id'] ?? null,
            promptVersion: $promptData['version'],
        );

        $response = $this->callAI($request);

        $parsed = json_decode($response->content, true);
        
        $semanticEntities = $parsed['semantic_entities'] ?? [];
        $semanticKeywords = $parsed['semantic_keywords'] ?? [];
        $searchIntent = $parsed['search_intent'] ?? null;

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: [
                'semantic_entities' => $semanticEntities,
                'semantic_keywords' => $semanticKeywords,
                'search_intent' => $searchIntent,
                'raw_analysis' => $response->content,
            ],
            tokens: $response->totalTokens,
            latency: $response->latencyMs
        );
    }
}
