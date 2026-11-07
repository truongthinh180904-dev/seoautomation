<?php

namespace App\Services\SEO\GA4;

use App\DTOs\SEO\Ga4PerformanceDTO;
use App\Models\GoogleAccount;
use App\Services\Integrations\AbstractExternalApiClient;
use App\Services\Integrations\GoogleOAuthService;
use Illuminate\Support\Facades\Log;

class GoogleAnalyticsService extends AbstractExternalApiClient
{
    protected const ADMIN_API_BASE = 'https://analyticsadmin.googleapis.com/v1beta';

    protected const DATA_API_BASE = 'https://analyticsdata.googleapis.com/v1beta';

    public function __construct(
        protected GoogleOAuthService $googleOAuthService
    ) {}

    /**
     * Retrieve list of accessible Google Analytics 4 accounts and properties.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public function getAccountSummaries(GoogleAccount $account): array
    {
        $account = $this->googleOAuthService->refreshTokenIfNeeded($account);

        $url = self::ADMIN_API_BASE.'/accountSummaries';

        $response = $this->get($url, [], [
            'Authorization' => "Bearer {$account->access_token}",
        ]);

        if ($response->failed()) {
            Log::warning("[GoogleAnalyticsService] Failed to fetch account summaries for GoogleAccount #{$account->id}", [
                'status' => $response->status(),
                'error' => $response->json('error') ?? $response->body(),
            ]);

            throw new \RuntimeException('Failed to fetch accounts from Google Analytics: '.($response->json('error.message') ?? $response->body()));
        }

        $accountSummaries = $response->json('accountSummaries') ?? [];
        $flatProperties = [];

        foreach ($accountSummaries as $accSummary) {
            $accountResource = (string) ($accSummary['account'] ?? '');
            $accountName = (string) ($accSummary['displayName'] ?? '');
            $propertySummaries = $accSummary['propertySummaries'] ?? [];

            foreach ($propertySummaries as $prop) {
                $propertyResource = (string) ($prop['property'] ?? '');
                if ($propertyResource === '') {
                    continue;
                }

                $numericId = str_starts_with($propertyResource, 'properties/')
                    ? substr($propertyResource, strlen('properties/'))
                    : $propertyResource;

                $flatProperties[] = [
                    'property_id' => $propertyResource,
                    'numeric_id' => $numericId,
                    'display_name' => (string) ($prop['displayName'] ?? "GA4 Property {$numericId}"),
                    'property_type' => (string) ($prop['propertyType'] ?? 'PROPERTY_TYPE_ORDINARY'),
                    'account_id' => $accountResource,
                    'account_name' => $accountName,
                ];
            }
        }

        return $flatProperties;
    }

    /**
     * Get metadata and configuration details for a specific GA4 property.
     *
     * @return array<string, mixed>|null
     */
    public function getProperty(GoogleAccount $account, string $propertyId): ?array
    {
        $account = $this->googleOAuthService->refreshTokenIfNeeded($account);

        $formattedProperty = str_starts_with($propertyId, 'properties/')
            ? $propertyId
            : 'properties/'.$propertyId;

        $url = self::ADMIN_API_BASE.'/'.$formattedProperty;

        $response = $this->get($url, [], [
            'Authorization' => "Bearer {$account->access_token}",
        ]);

        if ($response->failed()) {
            Log::warning("[GoogleAnalyticsService] Failed to get property details for {$propertyId}", [
                'status' => $response->status(),
                'error' => $response->json('error') ?? $response->body(),
            ]);

            return null;
        }

        $data = $response->json() ?? [];
        $resourceName = (string) ($data['name'] ?? $formattedProperty);
        $numericId = str_starts_with($resourceName, 'properties/')
            ? substr($resourceName, strlen('properties/'))
            : $resourceName;

        return [
            'property_id' => $resourceName,
            'numeric_id' => $numericId,
            'display_name' => (string) ($data['displayName'] ?? "GA4 Property {$numericId}"),
            'time_zone' => (string) ($data['timeZone'] ?? 'UTC'),
            'currency_code' => (string) ($data['currencyCode'] ?? 'USD'),
            'parent_account' => (string) ($data['parent'] ?? ''),
            'create_time' => (string) ($data['createTime'] ?? ''),
        ];
    }

    /**
     * Test and verify connection & access permissions for a GA4 property.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function testConnection(GoogleAccount $account, string $propertyId): array
    {
        $details = $this->getProperty($account, $propertyId);

        if (! $details) {
            throw new \RuntimeException("Không thể xác minh quyền truy cập Google Analytics 4 Property: {$propertyId}");
        }

        return [
            'connected' => true,
            'is_verified' => true,
            'property_id' => $details['property_id'],
            'numeric_id' => $details['numeric_id'],
            'property_name' => $details['display_name'],
            'time_zone' => $details['time_zone'],
            'currency_code' => $details['currency_code'],
            'account_id' => $details['parent_account'],
        ];
    }

    /**
     * Run a Google Analytics 4 report using Data API v1beta.
     *
     * @param  array<int, string>  $dimensions
     * @param  array<int, string>  $metrics
     * @return array<int, Ga4PerformanceDTO>
     *
     * @throws \RuntimeException
     */
    public function runReport(
        GoogleAccount $account,
        string $propertyId,
        string $startDate,
        string $endDate,
        array $dimensions = ['date', 'landingPagePlusQueryString'],
        array $metrics = ['sessions', 'totalUsers', 'screenPageViews', 'engagementRate', 'conversions'],
        int $limit = 10000,
        int $offset = 0
    ): array {
        $account = $this->googleOAuthService->refreshTokenIfNeeded($account);

        $formattedProperty = str_starts_with($propertyId, 'properties/')
            ? $propertyId
            : 'properties/'.$propertyId;

        $url = self::DATA_API_BASE.'/'.$formattedProperty.':runReport';

        $payload = [
            'dateRanges' => [
                [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ],
            ],
            'dimensions' => array_map(fn (string $d) => ['name' => $d], $dimensions),
            'metrics' => array_map(fn (string $m) => ['name' => $m], $metrics),
            'limit' => min(max($limit, 1), 100000),
            'offset' => max($offset, 0),
        ];

        $response = $this->post($url, $payload, [
            'Authorization' => "Bearer {$account->access_token}",
            'Content-Type' => 'application/json',
        ]);

        if ($response->failed()) {
            Log::warning("[GoogleAnalyticsService] runReport failed for {$propertyId}", [
                'status' => $response->status(),
                'error' => $response->json('error') ?? $response->body(),
            ]);

            throw new \RuntimeException('Failed to run GA4 Analytics Report: '.($response->json('error.message') ?? $response->body()));
        }

        $rows = $response->json('rows') ?? [];
        $metricDTOs = [];

        foreach ($rows as $row) {
            $metricDTOs[] = Ga4PerformanceDTO::fromReportRow(
                row: $row,
                dimensionHeaders: $dimensions,
                metricHeaders: $metrics,
                defaultDate: $startDate
            );
        }

        return $metricDTOs;
    }

    /**
     * Fetch page performance across multiple batches up to $maxRows.
     *
     * @param  array<int, string>  $dimensions
     * @param  array<int, string>  $metrics
     * @return array<int, Ga4PerformanceDTO>
     */
    public function fetchPagePerformance(
        GoogleAccount $account,
        string $propertyId,
        string $startDate,
        string $endDate,
        array $dimensions = ['date', 'landingPagePlusQueryString'],
        array $metrics = ['sessions', 'totalUsers', 'screenPageViews', 'engagementRate', 'conversions'],
        int $maxRows = 50000
    ): array {
        $allMetrics = [];
        $offset = 0;
        $batchSize = 10000;

        while (count($allMetrics) < $maxRows) {
            $batch = $this->runReport(
                account: $account,
                propertyId: $propertyId,
                startDate: $startDate,
                endDate: $endDate,
                dimensions: $dimensions,
                metrics: $metrics,
                limit: $batchSize,
                offset: $offset
            );

            if (empty($batch)) {
                break;
            }

            $allMetrics = array_merge($allMetrics, $batch);

            if (count($batch) < $batchSize) {
                break;
            }

            $offset += $batchSize;
        }

        return $allMetrics;
    }
}
