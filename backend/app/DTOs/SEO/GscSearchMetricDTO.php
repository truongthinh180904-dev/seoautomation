<?php

namespace App\DTOs\SEO;

use JsonSerializable;

class GscSearchMetricDTO implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $date,
        public readonly string $query,
        public readonly string $pageUrl,
        public readonly int $clicks = 0,
        public readonly int $impressions = 0,
        public readonly float $ctr = 0.0,
        public readonly float $position = 0.0,
        public readonly string $country = 'vnm',
        public readonly string $device = 'DESKTOP',
        public readonly array $raw = []
    ) {}

    /**
     * Instantiate DTO from a Google Search Console API row response.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $dimensions
     */
    public static function fromApiResponse(
        array $row,
        array $dimensions = ['query', 'page', 'date', 'device', 'country'],
        ?string $defaultDate = null
    ): self {
        $keys = $row['keys'] ?? [];
        $keyMap = array_flip($dimensions);

        $date = isset($keyMap['date'], $keys[$keyMap['date']])
            ? (string) $keys[$keyMap['date']]
            : ($defaultDate ?? date('Y-m-d'));

        $query = isset($keyMap['query'], $keys[$keyMap['query']])
            ? (string) $keys[$keyMap['query']]
            : '';

        $pageUrl = isset($keyMap['page'], $keys[$keyMap['page']])
            ? (string) $keys[$keyMap['page']]
            : '';

        $country = isset($keyMap['country'], $keys[$keyMap['country']])
            ? (string) $keys[$keyMap['country']]
            : 'vnm';

        $device = isset($keyMap['device'], $keys[$keyMap['device']])
            ? strtoupper((string) $keys[$keyMap['device']])
            : 'DESKTOP';

        $clicks = isset($row['clicks']) ? (int) $row['clicks'] : 0;
        $impressions = isset($row['impressions']) ? (int) $row['impressions'] : 0;
        $ctr = isset($row['ctr']) ? (float) $row['ctr'] : 0.0;
        $position = isset($row['position']) ? (float) $row['position'] : 0.0;

        return new self(
            date: $date,
            query: $query,
            pageUrl: $pageUrl,
            clicks: $clicks,
            impressions: $impressions,
            ctr: $ctr,
            position: $position,
            country: $country,
            device: $device,
            raw: $row
        );
    }

    /**
     * Convert DTO to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'query' => $this->query,
            'page_url' => $this->pageUrl,
            'clicks' => $this->clicks,
            'impressions' => $this->impressions,
            'ctr' => $this->ctr,
            'position' => $this->position,
            'country' => $this->country,
            'device' => $this->device,
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
