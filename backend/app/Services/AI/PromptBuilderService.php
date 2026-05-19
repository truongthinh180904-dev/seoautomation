<?php

namespace App\Services\AI;

use App\Enums\AgentType;
use App\Models\AIPromptVersion;
use Illuminate\Support\Facades\Cache;

class PromptBuilderService
{
    /**
     * Builds the prompt for a given agent and variables.
     * Supports A/B split: if a tenant has multiple active prompts for the same agent,
     * selects one deterministically (based on tenant_id % active_count).
     *
     * @return array{system_prompt: string, user_prompt: string, version: string}
     */
    public function build(AgentType $agentType, array $variables = [], ?int $tenantId = null): array
    {
        $promptVersion = $this->getActivePrompt($agentType, $tenantId);

        if (!$promptVersion) {
            throw new \RuntimeException("No active prompt found for agent: {$agentType->value}");
        }

        $systemPrompt = $this->substituteVariables($promptVersion->system_prompt ?? '', $variables);
        $userPrompt   = $this->substituteVariables($promptVersion->user_prompt_template, $variables);

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt'   => $userPrompt,
            'version'       => $promptVersion->version,
            'prompt_id'     => $promptVersion->id,
        ];
    }

    /**
     * Returns the active prompt for a given agent and tenant.
     * A/B split: if tenant has multiple active prompts, picks deterministically by (tenant_id mod count).
     */
    protected function getActivePrompt(AgentType $agentType, ?int $tenantId): ?AIPromptVersion
    {
        $cacheKey = "ai_prompt_active:{$agentType->value}:" . ($tenantId ?? 'default');

        $promptId = Cache::remember($cacheKey, 3600, function () use ($agentType, $tenantId) {
            if ($tenantId) {
                $tenantPrompts = AIPromptVersion::where('agent_type', $agentType->value)
                    ->where('is_active', true)
                    ->where('tenant_id', $tenantId)
                    ->orderBy('version', 'desc')
                    ->get();

                if ($tenantPrompts->count() > 1) {
                    $bucket = $tenantId % $tenantPrompts->count();

                    return $tenantPrompts[$bucket]->id;
                }

                if ($tenantPrompts->count() === 1) {
                    return $tenantPrompts->first()->id;
                }
            }

            return AIPromptVersion::where('agent_type', $agentType->value)
                ->where('is_active', true)
                ->whereNull('tenant_id')
                ->orderBy('version', 'desc')
                ->value('id');
        });

        if (!is_numeric($promptId)) {
            Cache::forget($cacheKey);
            $promptId = $this->resolveActivePromptId($agentType, $tenantId);
            if ($promptId) {
                Cache::put($cacheKey, $promptId, 3600);
            }
        }

        return $promptId ? AIPromptVersion::find((int) $promptId) : null;
    }

    protected function resolveActivePromptId(AgentType $agentType, ?int $tenantId): ?int
    {
        if ($tenantId) {
            $tenantPrompts = AIPromptVersion::where('agent_type', $agentType->value)
                ->where('is_active', true)
                ->where('tenant_id', $tenantId)
                ->orderBy('version', 'desc')
                ->get(['id']);

            if ($tenantPrompts->count() > 1) {
                return $tenantPrompts[$tenantId % $tenantPrompts->count()]->id;
            }

            if ($tenantPrompts->count() === 1) {
                return $tenantPrompts->first()->id;
            }
        }

        return AIPromptVersion::where('agent_type', $agentType->value)
            ->where('is_active', true)
            ->whereNull('tenant_id')
            ->orderBy('version', 'desc')
            ->value('id');
    }

    /**
     * Invalidate prompt cache for a given agent type (call after activate/deactivate).
     */
    public function invalidateCache(AgentType $agentType, ?int $tenantId = null): void
    {
        $cacheKey = "ai_prompt_active:{$agentType->value}:" . ($tenantId ?? 'default');
        Cache::forget($cacheKey);
    }

    /**
     * Update performance_score on a prompt version based on article SEO score.
     * Called when an article achieves a high SEO score.
     */
    public function recordPerformance(int $promptId, float $seoScore): void
    {
        $prompt = AIPromptVersion::find($promptId);
        if (!$prompt) return;

        // Exponential moving average with alpha = 0.3
        if ($prompt->performance_score === null) {
            $prompt->performance_score = $seoScore;
        } else {
            $prompt->performance_score = round(
                0.3 * $seoScore + 0.7 * $prompt->performance_score,
                2
            );
        }

        $prompt->save();
    }

    protected function substituteVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }
}
