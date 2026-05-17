<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;
use Illuminate\Support\Facades\Log;

class InternalLinkingAgent extends BaseAgent
{
    public function getType(): AgentType
    {
        return AgentType::INTERNAL_LINKING;
    }

    protected function run(array $context): AgentResultDTO
    {
        $articleContent = $context['article_content'] ?? null;
        $relatedPosts = $context['related_posts'] ?? [];
        $tenantId = $context['tenant_id'] ?? null;

        if (!$articleContent || !$tenantId || empty($relatedPosts)) {
            return AgentResultDTO::success($this->getType(), ['content' => $articleContent, 'links_added' => []]);
        }

        $postsSummary = json_encode($relatedPosts, JSON_UNESCAPED_UNICODE);

        $promptData = $this->promptBuilderService->build($this->getType(), [
            'article_content' => mb_substr($articleContent, 0, 15000), // Protect token limit
            'related_posts' => $postsSummary,
        ], $tenantId);

        $systemPrompt = $promptData['system_prompt'] . "\n\nYou MUST return the response ONLY as a JSON object with this exact structure: {\"modified_content\": \"HTML string with <a> tags inserted\", \"links_added\": [{\"url\": \"string\", \"anchor_text\": \"string\"}]}. Do not include markdown formatting.";

        $request = new AIRequestDTO(
            systemPrompt: $systemPrompt,
            userPrompt: $promptData['user_prompt'],
            model: 'gpt-4o', // Need gpt-4o for complex HTML manipulation
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
            Log::warning("InternalLinkingAgent failed to parse JSON: " . json_last_error_msg());
            return AgentResultDTO::success($this->getType(), ['content' => $articleContent, 'links_added' => []]);
        }

        $modifiedContent = $parsed['modified_content'] ?? $articleContent;
        $linksAdded = $parsed['links_added'] ?? [];

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: [
                'content' => $modifiedContent,
                'links_added' => is_array($linksAdded) ? $linksAdded : [],
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
