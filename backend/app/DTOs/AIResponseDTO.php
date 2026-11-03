<?php

namespace App\DTOs;

use App\Enums\AIProvider;
use Spatie\LaravelData\Data;

class AIResponseDTO extends Data
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

        if (str_contains($model, 'gpt-4o-mini')) {
            $inputPricePerM = 0.15;
            $outputPricePerM = 0.60;
        } elseif (str_contains($model, 'gpt-4o')) {
            $inputPricePerM = 5.0;
            $outputPricePerM = 15.0;
        } elseif (str_contains($model, 'claude-3-5-sonnet')) {
            $inputPricePerM = 3.0;
            $outputPricePerM = 15.0;
        } elseif (str_contains($model, 'gemini-2.5-flash')) {
            $inputPricePerM = 0.30;
            $outputPricePerM = 2.50;
        } elseif (str_contains($model, 'gemini-1.5-flash')) {
            $inputPricePerM = 0.075;
            $outputPricePerM = 0.30;
        } elseif (str_contains($model, 'gemini-1.5-pro')) {
            $inputPricePerM = 1.25;
            $outputPricePerM = 5.0;
        } elseif (str_contains($model, 'deepseek-chat')) {
            $inputPricePerM = 0.14;
            $outputPricePerM = 0.28;
        } else {
            $fallback = config('ai_costs.fallback_model_rate', ['input' => 1.0, 'output' => 2.0]);
            $inputPricePerM = (float) ($fallback['input'] ?? 1.0);
            $outputPricePerM = (float) ($fallback['output'] ?? 2.0);
        }

        $inputCost = ($this->promptTokens / 1000000) * $inputPricePerM;
        $outputCost = ($this->completionTokens / 1000000) * $outputPricePerM;

        return round($inputCost + $outputCost, 8);
    }
}
