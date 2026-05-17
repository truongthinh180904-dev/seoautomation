<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

readonly class SerpResultDTO extends Data
{
    public function __construct(
        public int $position,
        public string $url,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $domain = null,
        public ?array $rawData = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            position: $data['position'] ?? 0,
            url: $data['url'] ?? '',
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            domain: $data['domain'] ?? null,
            rawData: $data['rawData'] ?? $data['raw_data'] ?? null,
        );
    }
}
