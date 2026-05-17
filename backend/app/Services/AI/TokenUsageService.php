<?php

namespace App\Services\AI;

class TokenUsageService
{
    public function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $rates = [
            'gpt-4o' => ['input' => 5.0, 'output' => 15.0],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'claude-3-5-sonnet-20241022' => ['input' => 3.0, 'output' => 15.0],
            'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.0],
            'deepseek-chat' => ['input' => 0.14, 'output' => 0.28],
        ];

        $rate = $rates[$model] ?? ['input' => 5.0, 'output' => 15.0];

        $inputCost = ($promptTokens / 1000000) * $rate['input'];
        $outputCost = ($completionTokens / 1000000) * $rate['output'];

        return $inputCost + $outputCost;
    }
}
