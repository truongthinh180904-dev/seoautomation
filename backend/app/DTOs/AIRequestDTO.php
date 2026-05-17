<?php

namespace App\DTOs;

use App\Enums\AgentType;
use Spatie\LaravelData\Data;

readonly class AIRequestDTO extends Data
{
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt,
        public string $model,
        public AgentType $agentType,
        public int $tenantId,
        public int $maxTokens = 4000,
        public float $temperature = 0.7,
        public ?int $articleId = null,
        public ?int $keywordId = null,
        public ?string $promptVersion = null,
    ) {}

    public function toHash(): string
    {
        return md5(json_encode([$this->systemPrompt, $this->userPrompt, $this->model]));
    }
}
