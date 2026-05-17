<?php

return [
    'serp_api_key' => env('SERP_API_KEY'),
    'max_competitors' => env('SEO_MAX_COMPETITORS', 10),
    'min_word_count' => env('SEO_MIN_WORD_COUNT', 1500),
    'target_keyword_density' => env('SEO_TARGET_KEYWORD_DENSITY', 1.5),
    'internal_links_per_article' => env('SEO_INTERNAL_LINKS_PER_ARTICLE', 4),
];
