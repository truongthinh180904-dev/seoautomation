<?php

namespace App\DTOs;

use App\Models\Keyword;
use Spatie\LaravelData\Data;

readonly class KeywordDTO extends Data
{
    public function __construct(
        public int $tenantId,
        public string $keyword,
        public string $language = 'vi',
        public int $priority = 5,
        public ?int $id = null,
        public ?int $searchVolume = null,
        public ?int $difficulty = null,
        public ?string $searchIntent = null,
        public ?int $wordpressSiteId = null,
    ) {}

    public static function fromModel(Keyword $keyword): self
    {
        return new self(
            tenantId: $keyword->tenant_id,
            keyword: $keyword->keyword,
            language: $keyword->language ?? 'vi',
            priority: $keyword->priority ?? 5,
            id: $keyword->id,
            searchVolume: $keyword->search_volume,
            difficulty: $keyword->difficulty,
            searchIntent: $keyword->search_intent,
            wordpressSiteId: $keyword->wordpress_site_id,
        );
    }
}
