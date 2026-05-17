<?php

namespace App\DTOs;

use App\Enums\AIProvider;
use Spatie\LaravelData\Data;

readonly class AIResponseDTO extends Data
{
    public function __construct(
        public string $content,
        public AIProvider $provider,
        public string $model,
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public int $latencyMs,
        public array $rawResponse,
    ) {}

    public function totalCostUsd(): float
    {
        $model = strtolower($this->model);
        
        $inputPricePerM = 0;
        $outputPricePerM = 0;

        if (str_contains($model, 'gpt-4o')) {
            $inputPricePerM = 5.0;
            $outputPricePerM = 15.0;
        } elseif (str_contains($model, 'claude-3-5-sonnet')) {
            $inputPricePerM = 3.0;
            $outputPricePerM = 15.0;
        } elseif (str_contains($model, 'gemini-1.5-pro')) {
            $inputPricePerM = 1.25;
            $outputPricePerM = 5.0;
        } else {
            $inputPricePerM = 1.0;
            $outputPricePerM = 2.0;
        }

        $inputCost = ($this->promptTokens / 1000000) * $inputPricePerM;
        $outputCost = ($this->completionTokens / 1000000) * $outputPricePerM;

        return $inputCost + $outputCost;
    }
}
