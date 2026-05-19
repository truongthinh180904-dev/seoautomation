<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class PublishingDTO extends Data
{
    public function __construct(
        public string $title,
        public string $content,
        public string $publishStatus,
        public ?string $excerpt = null,
        public ?string $slug = null,
        public ?int $authorId = null,
        public array $categoryIds = [],
        public array $tagIds = [],
        public array $tagNames = [],
        public ?int $featuredMediaId = null,
        public ?string $seoTitle = null,
        public ?string $seoDescription = null,
        public ?string $scheduledAt = null,
        public string $commentStatus = 'open',
        public string $pingStatus = 'open',
        public string $postType = 'post',
        public array $meta = [],
        public ?string $canonicalUrl = null,
    ) {}
}
