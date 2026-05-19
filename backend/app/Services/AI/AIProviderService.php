<?php

namespace App\Services\AI;

use App\DTOs\AIRequestDTO;
use App\DTOs\AIResponseDTO;
use App\Enums\AIProvider;
use App\Exceptions\AI\AllProvidersFailedException;
use App\Exceptions\AI\ProviderException;
use App\Exceptions\AI\RateLimitException;
use App\Models\AILog;
use App\Providers\AI\Contracts\AIProviderInterface;
use App\Services\Cost\CostTrackingService;
use App\Services\Notifications\NotificationService;
use App\Enums\NotificationType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AIProviderService
{
    /** @var AIProviderInterface[] */
    protected array $providers = [];

    protected array $providerChain = [];

    // Per-provider rate limit: max hits within window (in seconds)
    protected array $providerRateLimits = [
        'openai'    => ['max' => 50, 'window' => 60],
        'anthropic' => ['max' => 40, 'window' => 60],
    ];

    public function __construct(
        iterable $providers,
        protected TokenUsageService $tokenUsageService,
        protected CostTrackingService $costTrackingService,
        protected NotificationService $notificationService
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->getProvider()->value] = $provider;
        }

        $this->providerChain = $this->buildProviderChain();
    }

    public function complete(AIRequestDTO $request): AIResponseDTO
    {
        $lastException = null;

        foreach ($this->providerChain as $providerEnum) {
            $providerKey = $providerEnum->value;

            if (!isset($this->providers[$providerKey])) {
                continue;
            }

            $provider = $this->providers[$providerKey];

            if (!$provider->isAvailable()) {
                continue;
            }

            // Check Redis-based per-provider rate limit
            if ($this->isProviderRateLimited($providerKey)) {
                Log::warning("AIProviderService: {$providerKey} is Redis rate-limited, skipping.");
                $lastException = new RateLimitException("Provider {$providerKey} is locally rate-limited (Redis).");
                continue;
            }

            try {
                $response = $provider->complete($request);

                // Record successful call in Redis for rate tracking
                $this->incrementProviderUsage($providerKey);

                $costUsd = $this->tokenUsageService->calculateCost(
                    $response->model,
                    $response->promptTokens,
                    $response->completionTokens
                );

                $this->logRequest($request, $response, 'success', $costUsd);
                $this->logUsage($request, $response, 'success', $costUsd);

                return $response;

            } catch (RateLimitException $e) {
                $lastException = $e;
                // Mark provider as rate-limited in Redis for the window duration
                $this->markProviderRateLimitedInRedis($providerKey);
                $this->logError($request, $providerEnum, 'rate_limited', $e->getMessage());
                $this->logUsageError($request, $providerEnum, 'rate_limited', $e->getMessage());
                continue;
            } catch (ProviderException $e) {
                $lastException = $e;
                $this->logError($request, $providerEnum, 'failed', $e->getMessage());
                $this->logUsageError($request, $providerEnum, 'failed', $e->getMessage());
                continue;
            }
        }

        $errorMsg = "All AI providers failed. Last error: " . ($lastException ? $lastException->getMessage() : 'No available providers.');

        $this->alertAdmin($request, $errorMsg, $lastException);

        throw new AllProvidersFailedException($errorMsg);
    }

    protected function buildProviderChain(): array
    {
        $configured = array_filter(array_merge(
            [config('ai.default_provider', 'openai')],
            config('ai.fallback_chain', [])
        ));

        $chain = [];

        foreach ($configured as $provider) {
            $providerEnum = AIProvider::tryFrom((string) $provider);

            if ($providerEnum && !in_array($providerEnum, $chain, true)) {
                $chain[] = $providerEnum;
            }
        }

        return $chain ?: [AIProvider::OPENAI];
    }

    /**
     * Check if provider has exceeded Redis-tracked rate limit window.
     */
    protected function isProviderRateLimited(string $providerKey): bool
    {
        $blockedKey = "ai_provider:blocked:{$providerKey}";
        return (bool) Redis::connection('cache')->exists($blockedKey);
    }

    /**
     * Increment usage counter for provider in Redis (sliding window).
     */
    protected function incrementProviderUsage(string $providerKey): void
    {
        $limits = $this->providerRateLimits[$providerKey] ?? null;
        if (!$limits) return;

        $usageKey = "ai_provider:usage:{$providerKey}";
        $redis = Redis::connection('cache');

        $current = (int) $redis->incr($usageKey);
        if ($current === 1) {
            $redis->expire($usageKey, $limits['window']);
        }

        // If usage exceeds soft limit, preemptively back off
        if ($current >= $limits['max']) {
            $this->markProviderRateLimitedInRedis($providerKey, ttl: 30);
        }
    }

    /**
     * Mark provider as blocked in Redis for TTL seconds (received 429 from API).
     */
    protected function markProviderRateLimitedInRedis(string $providerKey, int $ttl = 60): void
    {
        $blockedKey = "ai_provider:blocked:{$providerKey}";
        Redis::connection('cache')->setex($blockedKey, $ttl, 1);
        Log::warning("AIProviderService: {$providerKey} marked as rate-limited in Redis for {$ttl}s.");
    }

    protected function alertAdmin(AIRequestDTO $request, string $errorMsg, ?\Throwable $lastException): void
    {
        if (!$request->tenantId) {
            return;
        }

        try {
            $this->notificationService->send(
                tenantId: $request->tenantId,
                type: NotificationType::ARTICLE_QA_FAILED,
                subject: 'AI providers failed',
                message: $errorMsg . "\n\nLast error: " . ($lastException?->getMessage() ?? 'N/A'),
                data: [
                    'article_id' => $request->articleId,
                    'keyword_id' => $request->keywordId,
                    'agent_type' => $request->agentType->value,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("Failed to queue AI provider failure notification: " . $e->getMessage());
        }
    }

    protected function logRequest(AIRequestDTO $request, AIResponseDTO $response, string $status, float $costUsd): void
    {
        AILog::create([
            'tenant_id'        => $request->tenantId,
            'article_id'       => $request->articleId,
            'keyword_id'       => $request->keywordId,
            'agent_type'       => $request->agentType->value,
            'provider'         => $response->provider->value,
            'model'            => $response->model,
            'prompt_version'   => $request->promptVersion,
            'prompt_tokens'    => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'total_tokens'     => $response->totalTokens,
            'cost_usd'         => $costUsd,
            'latency_ms'       => $response->latencyMs,
            'status'           => $status,
            'error_message'    => null,
            'request_hash'     => $request->toHash(),
        ]);
    }

    protected function logError(AIRequestDTO $request, AIProvider $provider, string $status, string $errorMessage): void
    {
        AILog::create([
            'tenant_id'        => $request->tenantId,
            'article_id'       => $request->articleId,
            'keyword_id'       => $request->keywordId,
            'agent_type'       => $request->agentType->value,
            'provider'         => $provider->value,
            'model'            => $request->model,
            'prompt_version'   => $request->promptVersion,
            'prompt_tokens'    => 0,
            'completion_tokens' => 0,
            'total_tokens'     => 0,
            'cost_usd'         => 0,
            'latency_ms'       => 0,
            'status'           => $status,
            'error_message'    => $errorMessage,
            'request_hash'     => $request->toHash(),
        ]);
    }

    protected function logUsage(AIRequestDTO $request, AIResponseDTO $response, string $status, float $costUsd): void
    {
        if (!$request->tenantId) {
            return;
        }

        $this->costTrackingService->logUsage([
            'tenant_id' => $request->tenantId,
            'article_id' => $request->articleId,
            'keyword_id' => $request->keywordId,
            'job_type' => $request->agentType->value,
            'provider' => $response->provider->value,
            'model' => $response->model,
            'prompt_tokens' => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'total_tokens' => $response->totalTokens,
            'cost_usd' => $costUsd,
            'duration_ms' => $response->latencyMs,
            'status' => $status,
        ]);
    }

    protected function logUsageError(AIRequestDTO $request, AIProvider $provider, string $status, string $errorMessage): void
    {
        if (!$request->tenantId) {
            return;
        }

        $this->costTrackingService->logUsage([
            'tenant_id' => $request->tenantId,
            'article_id' => $request->articleId,
            'keyword_id' => $request->keywordId,
            'job_type' => $request->agentType->value,
            'provider' => $provider->value,
            'model' => $request->model,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
