<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class CompetitorDataDTO extends Data
{
    public function __construct(
        public string $url,
        public ?int $wordCount = null,
        public ?array $headingStructure = null,
        public ?array $semanticEntities = null,
        public ?array $semanticKeywords = null,
        public ?string $searchIntent = null,
        public ?array $faqs = null,
        public ?array $articleStructure = null,
        public ?float $topicalRelevance = null,
    ) {}

    public function toSummary(): string
    {
        $lines = [
            "URL: {$this->url}",
            "Word Count: " . ($this->wordCount ?? 'N/A'),
            "Search Intent: " . ($this->searchIntent ?? 'N/A'),
            "Topical Relevance: " . ($this->topicalRelevance ?? 'N/A'),
        ];

        if (!empty($this->headingStructure)) {
            $lines[] = "Headings: " . json_encode($this->headingStructure, JSON_UNESCAPED_UNICODE);
        }

        if (!empty($this->semanticKeywords)) {
            $lines[] = "Keywords: " . implode(', ', $this->semanticKeywords);
        }

        if (!empty($this->faqs)) {
            $lines[] = "FAQs: " . json_encode($this->faqs, JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $lines);
    }
}
