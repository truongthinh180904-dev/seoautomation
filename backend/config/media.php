<?php

return [
    'verify_ssl' => env('MEDIA_HTTP_VERIFY_SSL', env('WP_HTTP_VERIFY_SSL', true)),
    'optimization' => [
        'enabled' => filter_var(env('MEDIA_OPTIMIZATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'max_width' => (int) env('MEDIA_OPTIMIZATION_MAX_WIDTH', 1200),
        'jpeg_quality' => (int) env('MEDIA_OPTIMIZATION_JPEG_QUALITY', 82),
        'webp_quality' => (int) env('MEDIA_OPTIMIZATION_WEBP_QUALITY', 82),
        'png_compression' => (int) env('MEDIA_OPTIMIZATION_PNG_COMPRESSION', 6),
        'convert_to_jpeg' => filter_var(env('MEDIA_OPTIMIZATION_CONVERT_TO_JPEG', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
