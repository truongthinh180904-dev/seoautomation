<?php

namespace App\Services\SEO\GSC;

use App\DTOs\SEO\GscSearchMetricDTO;
use App\Models\GoogleAccount;
use App\Services\Integrations\AbstractExternalApiClient;
use App\Services\Integrations\GoogleOAuthService;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsoleService extends AbstractExternalApiClient
{
    protected const BASE_SITES_URL = 'https://www.googleapis.com/webmasters/v3/sites';

    public function __construct(
        protected GoogleOAuthService $googleOAuthService
    ) {}

    /**
     * Retrieve list of verified Search Console sites/properties accessible by the Google Account.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public function getSites(GoogleAccount $account): array
    {
        $account = $this->googleOAuthService->refreshTokenIfNeeded($account);

        $response = $this->get(self::BASE_SITES_URL, [], [
            'Authorization' => "Bearer {$account->access_token}",
        ]);

        if ($response->failed()) {
            Log::warning("[GoogleSearchConsoleService] Failed to list sites for GoogleAccount #{$account->id}", [
                'status' => $response->status(),
                'error' => $response->json('error') ?? $response->body(),
            ]);

            throw new \RuntimeException('Failed to fetch properties from Google Search Console: '.($response->json('error.message') ?? $response->body()));
        }

        $siteEntries = $response->json('siteEntry') ?? [];
        $properties = [];

        foreach ($siteEntries as $entry) {
            $siteUrl = (string) ($entry['siteUrl'] ?? '');
            if ($siteUrl === '') {
                continue;
            }

            $isDomain = str_starts_with($siteUrl, 'sc-domain:');

            $properties[] = [
                'site_url' => $siteUrl,
                'permission_level' => (string) ($entry['permissionLevel'] ?? 'siteRestrictedUser'),
                'property_type' => $isDomain ? 'domain' : 'url_prefix',
                'is_domain' => $isDomain,
            ];
        }

        return $properties;
    }

    /**
     * Get details for a specific Search Console property.
     *
     * @return array<string, mixed>|null
     */
    public function getSite(GoogleAccount $account, string $siteUrl): ?array
    {
        $account = $this->googleOAuthService->refreshTokenIfNeeded($account);

        $encodedUrl = rawurlencode($siteUrl);
        $url = self::BASE_SITES_URL.'/'.$encodedUrl;

        $response = $this->get($url, [], [
            'Authorization' => "Bearer {$account->access_token}",
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $isDomain = str_starts_with($siteUrl, 'sc-domain:');

        return [
            'site_url' => (string) ($data['siteUrl'] ?? $siteUrl),
            'permission_level' => (string) ($data['permissionLevel'] ?? 'siteRestrictedUser'),
            'property_type' => $isDomain ? 'domain' : 'url_prefix',
            'is_domain' => $isDomain,
        ];
    }

    /**
     * Test and verify connection to a Search Console property.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function testConnection(GoogleAccount $account, string $siteUrl): array
    {
        $site = $this->getSite($account, $siteUrl);

        if (! $site) {
            throw new \RuntimeException("Không thể xác minh quyền truy cập Search Console Property: {$siteUrl}");
        }

        return [
            'connected' => true,
            'is_verified' => true,
            'site_url' => $site['site_url'],
            'permission_level' => $site['permission_level'],
            'property_type' => $site['property_type'],
        ];
    }

    /**
     * Query Search Console Search Analytics data.
     *
     * @param  array<int, string>  $dimensions
     * @param  array<int, mixed>  $dimensionFilterGroups
     * @return array<int, GscSearchMetricDTO>
     *
     * @throws \RuntimeException
     */
    public function querySearchAnalytics(
        GoogleAccount $account,
        string $siteUrl,
        string $startDate,
        string $endDate,
        array $dimensions = ['query', 'page', 'date', 'device', 'country'],
        int $rowLimit = 25000,
        int $startRow = 0,
        string $dataState = 'all',
        array $dimensionFilterGroups = []
    ): array {
        $account = $this->googleOAuthService->refreshTokenIfNeeded($account);

        $encodedUrl = rawurlencode($siteUrl);
        $url = self::BASE_SITES_URL.'/'.$encodedUrl.'/searchAnalytics/query';

        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => $dimensions,
            'rowLimit' => min(max($rowLimit, 1), 25000),
            'startRow' => max($startRow, 0),
            'dataState' => $dataState,
        ];

        if (! empty($dimensionFilterGroups)) {
            $payload['dimensionFilterGroups'] = $dimensionFilterGroups;
        }

        $response = $this->post($url, $payload, [
            'Authorization' => "Bearer {$account->access_token}",
            'Content-Type' => 'application/json',
        ]);

        if ($response->failed()) {
            Log::warning("[GoogleSearchConsoleService] Query search analytics failed for {$siteUrl}", [
                'status' => $response->status(),
                'error' => $response->json('error') ?? $response->body(),
            ]);

            throw new \RuntimeException('Failed to query Search Console Analytics: '.($response->json('error.message') ?? $response->body()));
        }

        $rows = $response->json('rows') ?? [];
        $metrics = [];

        foreach ($rows as $row) {
            $metrics[] = GscSearchMetricDTO::fromApiResponse($row, $dimensions, $startDate);
        }

        return $metrics;
    }

    /**
     * Fetch all search analytics across multiple pages up to $maxRows.
     *
     * @param  array<int, string>  $dimensions
     * @param  array<int, mixed>  $dimensionFilterGroups
     * @return array<int, GscSearchMetricDTO>
     */
    public function fetchAllSearchAnalytics(
        GoogleAccount $account,
        string $siteUrl,
        string $startDate,
        string $endDate,
        array $dimensions = ['query', 'page', 'date', 'device', 'country'],
        int $maxRows = 100000,
        string $dataState = 'all',
        array $dimensionFilterGroups = []
    ): array {
        $allMetrics = [];
        $startRow = 0;
        $batchSize = 25000;

        while (count($allMetrics) < $maxRows) {
            $batch = $this->querySearchAnalytics(
                account: $account,
                siteUrl: $siteUrl,
                startDate: $startDate,
                endDate: $endDate,
                dimensions: $dimensions,
                rowLimit: $batchSize,
                startRow: $startRow,
                dataState: $dataState,
                dimensionFilterGroups: $dimensionFilterGroups
            );

            if (empty($batch)) {
                break;
            }

            $allMetrics = array_merge($allMetrics, $batch);

            if (count($batch) < $batchSize) {
                break;
            }

            $startRow += $batchSize;
        }

        return $allMetrics;
    }
}
