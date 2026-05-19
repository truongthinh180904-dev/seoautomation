<?php

return [
    'serper_cost_per_call_usd' => 0.001,

    'models' => [
        // USD per 1M tokens.
        'gpt-4o' => ['input' => 5.00, 'output' => 15.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'deepseek-chat' => ['input' => 0.14, 'output' => 0.28],
    ],

    'fallback_model_rate' => ['input' => 5.00, 'output' => 15.00],
];
