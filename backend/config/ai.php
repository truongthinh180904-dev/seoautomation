<?php

return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 2000),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-opus'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.5-flash'),
            'fallback_models' => array_values(array_filter(explode(',', env('GEMINI_FALLBACK_MODELS', 'gemini-1.5-flash')))),
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'default_model' => env('DEEPSEEK_DEFAULT_MODEL', 'deepseek-chat'),
        ],
    ],

    'fallback_chain' => array_values(array_filter(explode(',', env('AI_FALLBACK_CHAIN', 'openai,anthropic,gemini')))),

    'http' => [
        'verify_ssl' => filter_var(env('AI_HTTP_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
