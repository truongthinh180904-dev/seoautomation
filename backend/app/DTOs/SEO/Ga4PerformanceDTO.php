<?php

namespace App\DTOs\SEO;

use JsonSerializable;

class Ga4PerformanceDTO implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $date,
        public readonly string $landingPage,
        public readonly int $sessions = 0,
        public readonly int $activeUsers = 0,
        public readonly int $totalUsers = 0,
        public readonly int $screenPageViews = 0,
        public readonly float $engagementRate = 0.0,
        public readonly float $bounceRate = 0.0,
        public readonly float $conversions = 0.0,
        public readonly float $averageSessionDuration = 0.0,
        public readonly string $channelGroup = 'Organic Search',
        public readonly string $device = 'DESKTOP',
        public readonly string $country = 'vnm',
        public readonly array $raw = []
    ) {}

    /**
     * Normalize landing page URL to a standard relative path.
     * E.g. 'https://example.com/blog/article-1?utm=source' => '/blog/article-1'
     */
    public static function normalizeLandingPage(string $rawUrl): string
    {
        $rawUrl = trim($rawUrl);
        if ($rawUrl === '' || $rawUrl === '/') {
            return '/';
        }

        // Strip query string and fragment
        $path = explode('?', $rawUrl)[0];
        $path = explode('#', $path)[0];

        // If absolute URL, extract path component
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsedPath) ? $parsedPath : '/';
        }

        // Ensure leading slash
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        // Remove trailing slash if path is longer than '/'
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * Create a DTO instance from a GA4 Data API report row or structured array.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $dimensionHeaders
     * @param  array<int, string>  $metricHeaders
     */
    public static function fromReportRow(
        array $row,
        array $dimensionHeaders = ['date', 'landingPagePlusQueryString'],
        array $metricHeaders = ['sessions', 'totalUsers', 'screenPageViews', 'engagementRate', 'conversions'],
        ?string $defaultDate = null
    ): self {
        $dimMap = [];
        if (isset($row['dimensionValues']) && is_array($row['dimensionValues'])) {
            foreach ($row['dimensionValues'] as $idx => $dimVal) {
                $headerName = $dimensionHeaders[$idx] ?? "dim_{$idx}";
                $dimMap[$headerName] = (string) ($dimVal['value'] ?? '');
            }
        }

        $metMap = [];
        if (isset($row['metricValues']) && is_array($row['metricValues'])) {
            foreach ($row['metricValues'] as $idx => $metVal) {
                $headerName = $metricHeaders[$idx] ?? "met_{$idx}";
                $metMap[$headerName] = (string) ($metVal['value'] ?? '0');
            }
        }

        // Date extraction and formatting (from YYYYMMDD to YYYY-MM-DD)
        $rawDate = $dimMap['date'] ?? (string) ($row['date'] ?? ($defaultDate ?? date('Y-m-d')));
        if (preg_match('/^\d{8}$/', $rawDate)) {
            $date = substr($rawDate, 0, 4).'-'.substr($rawDate, 4, 2).'-'.substr($rawDate, 6, 2);
        } else {
            $date = $rawDate;
        }

        // Landing page extraction
        $rawLandingPage = $dimMap['landingPagePlusQueryString']
            ?? $dimMap['landingPage']
            ?? $dimMap['pagePath']
            ?? (string) ($row['landing_page'] ?? ($row['page_path'] ?? '/'));

        $landingPage = self::normalizeLandingPage($rawLandingPage);

        // Extract metrics
        $sessions = (int) ($metMap['sessions'] ?? ($row['sessions'] ?? 0));
        $activeUsers = (int) ($metMap['activeUsers'] ?? ($row['active_users'] ?? 0));
        $totalUsers = (int) ($metMap['totalUsers'] ?? ($row['total_users'] ?? $activeUsers));
        $screenPageViews = (int) ($metMap['screenPageViews'] ?? ($row['screen_page_views'] ?? ($row['pageviews'] ?? 0)));
        $engagementRate = (float) ($metMap['engagementRate'] ?? ($row['engagement_rate'] ?? 0.0));
        $bounceRate = (float) ($metMap['bounceRate'] ?? ($row['bounce_rate'] ?? (1.0 - $engagementRate)));
        $conversions = (float) ($metMap['conversions'] ?? ($row['conversions'] ?? 0.0));
        $averageSessionDuration = (float) ($metMap['averageSessionDuration'] ?? ($row['average_session_duration'] ?? 0.0));

        // Additional dimensions
        $channelGroup = (string) ($dimMap['sessionDefaultChannelGroup'] ?? ($row['channel_group'] ?? 'Organic Search'));
        $device = strtoupper((string) ($dimMap['deviceCategory'] ?? ($row['device'] ?? 'DESKTOP')));
        $country = (string) ($dimMap['country'] ?? ($row['country'] ?? 'vnm'));

        return new self(
            date: $date,
            landingPage: $landingPage,
            sessions: $sessions,
            activeUsers: $activeUsers,
            totalUsers: $totalUsers,
            screenPageViews: $screenPageViews,
            engagementRate: $engagementRate,
            bounceRate: $bounceRate,
            conversions: $conversions,
            averageSessionDuration: $averageSessionDuration,
            channelGroup: $channelGroup,
            device: $device,
            country: $country,
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
            'landing_page' => $this->landingPage,
            'sessions' => $this->sessions,
            'active_users' => $this->activeUsers,
            'total_users' => $this->totalUsers,
            'screen_page_views' => $this->screenPageViews,
            'engagement_rate' => $this->engagementRate,
            'bounce_rate' => $this->bounceRate,
            'conversions' => $this->conversions,
            'average_session_duration' => $this->averageSessionDuration,
            'channel_group' => $this->channelGroup,
            'device' => $this->device,
            'country' => $this->country,
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
