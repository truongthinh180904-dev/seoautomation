<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

readonly class PublishingDTO extends Data
{
    public function __construct(
        public string $title,
        public string $content,
        public string $publishStatus,
        public array $categoryIds = [],
        public array $tagIds = [],
        public ?string $seoTitle = null,
        public ?string $seoDescription = null,
        public ?string $scheduledAt = null,
    ) {}
}
