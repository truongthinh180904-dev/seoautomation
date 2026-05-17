<?php

namespace App\DTOs;

use App\Enums\AgentType;
use Spatie\LaravelData\Data;

readonly class AgentResultDTO extends Data
{
    public function __construct(
        public AgentType $agentType,
        public bool $success,
        public array $data,
        public ?string $error = null,
        public int $tokensUsed = 0,
        public float $costUsd = 0.0,
        public int $latencyMs = 0,
    ) {}

    public static function success(AgentType $agent, array $data, int $tokens = 0, float $cost = 0.0, int $latency = 0): self
    {
        return new self(
            agentType: $agent,
            success: true,
            data: $data,
            error: null,
            tokensUsed: $tokens,
            costUsd: $cost,
            latencyMs: $latency,
        );
    }

    public static function failure(AgentType $agent, string $error): self
    {
        return new self(
            agentType: $agent,
            success: false,
            data: [],
            error: $error,
            tokensUsed: 0,
            costUsd: 0.0,
            latencyMs: 0,
        );
    }
}
