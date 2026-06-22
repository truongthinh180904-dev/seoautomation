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

    'image_generation' => [
        'enabled' => filter_var(env('AI_IMAGE_GENERATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'provider' => env('AI_IMAGE_PROVIDER', 'gemini'),
        'source_strategy' => env('AI_IMAGE_SOURCE_STRATEGY', 'hybrid'),
        'model' => env('GEMINI_IMAGE_MODEL', 'imagen-4.0-generate-001'),
        'default_inline_count' => (int) env('AI_DEFAULT_INLINE_IMAGES', 3),
        'max_inline_count' => (int) env('AI_MAX_INLINE_IMAGES', 10),
        'aspect_ratio' => env('AI_IMAGE_ASPECT_RATIO', '16:9'),
        'image_size' => env('AI_IMAGE_SIZE', '1K'),
        'stock' => [
            'enabled' => filter_var(env('STOCK_IMAGE_SEARCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'pexels_api_key' => env('PEXELS_API_KEY'),
            'unsplash_access_key' => env('UNSPLASH_ACCESS_KEY'),
        ],
    ],
];
