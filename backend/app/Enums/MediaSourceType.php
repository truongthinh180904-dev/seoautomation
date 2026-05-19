<?php

namespace App\Enums;

enum MediaSourceType: string
{
    case UPLOADED = 'uploaded';
    case EXTERNAL_URL = 'external_url';
    case AI_GENERATED = 'ai_generated';
    case WORDPRESS_EXISTING = 'wordpress_existing';
}
