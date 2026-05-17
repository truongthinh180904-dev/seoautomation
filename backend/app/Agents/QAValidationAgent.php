<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;

class QAValidationAgent extends BaseAgent
{
    public function getType(): AgentType
    {
        return AgentType::QA_VALIDATION;
    }

    protected function run(array $context): AgentResultDTO
    {
        $articleData = $context['article_data'] ?? [];
        $tenantId = $context['tenant_id'] ?? null;

        if (empty($articleData) || !$tenantId) {
            throw new \InvalidArgumentException("Missing required context fields.");
        }

        $promptData = $this->promptBuilderService->build($this->getType(), [
            'article_data' => json_encode($articleData, JSON_UNESCAPED_UNICODE),
        ], $tenantId);

        $systemPrompt = $promptData['system_prompt'] . "\n\nYou MUST return the response ONLY as a JSON object with this exact structure: {\"passed\": boolean, \"score\": number, \"checks\": [{\"name\": \"string\", \"passed\": boolean, \"message\": \"string\"}]}. Do not include markdown formatting.";

        $request = new AIRequestDTO(
            systemPrompt: $systemPrompt,
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
            return AgentResultDTO::failure($this->getType(), "Failed to parse JSON QA report: " . json_last_error_msg());
        }

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: [
                'qa_report' => $parsed,
                'score' => $parsed['score'] ?? null,
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
