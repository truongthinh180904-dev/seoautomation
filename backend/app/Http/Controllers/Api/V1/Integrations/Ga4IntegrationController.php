<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\SEO\ConnectGa4Request;
use App\Models\GoogleAccount;
use App\Models\Website;
use App\Models\WebsiteGoogleAnalytics;
use App\Services\SEO\GA4\GoogleAnalyticsService;
use App\Traits\BelongsToWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Ga4IntegrationController extends Controller
{
    public function __construct(
        protected GoogleAnalyticsService $ga4Service
    ) {}

    /**
     * List all Google Analytics 4 properties accessible by the workspace's Google account.
     */
    public function properties(Request $request, int $id): JsonResponse
    {
        $website = $this->getAuthorizedWebsite($request, $id);
        $workspaceId = $website->workspace_id;

        $googleAccount = GoogleAccount::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('status', '!=', 'revoked')
            ->latest()
            ->first();

        if (! $googleAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa có tài khoản Google nào được kết nối với Không gian làm việc này.',
                'needs_google_connect' => true,
                'properties' => [],
            ], 422);
        }

        try {
            $flatProperties = $this->ga4Service->getAccountSummaries($googleAccount);

            $currentGa4 = WebsiteGoogleAnalytics::withoutGlobalScopes()
                ->where('website_id', $website->id)
                ->first();

            $properties = array_map(function (array $prop) use ($currentGa4) {
                $isConnected = $currentGa4 !== null && (
                    $currentGa4->property_id === $prop['property_id'] ||
                    $currentGa4->getCleanPropertyId() === $prop['numeric_id']
                );

                return array_merge($prop, [
                    'is_connected' => $isConnected,
                ]);
            }, $flatProperties);

            return response()->json([
                'success' => true,
                'properties' => $properties,
                'connected_property' => $currentGa4,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách Property từ Google Analytics 4: '.$e->getMessage(),
                'properties' => [],
            ], 400);
        }
    }

    /**
     * Connect a GA4 property to the website and store timezone & currency.
     */
    public function connect(ConnectGa4Request $request, int $id): JsonResponse
    {
        $website = $this->getAuthorizedWebsite($request, $id);
        $workspaceId = $website->workspace_id;

        $googleAccountId = $request->input('google_account_id');
        if ($googleAccountId) {
            $googleAccount = GoogleAccount::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('id', $googleAccountId)
                ->firstOrFail();
        } else {
            $googleAccount = GoogleAccount::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('status', '!=', 'revoked')
                ->latest()
                ->firstOrFail();
        }

        $propertyId = (string) $request->input('property_id');

        try {
            $testResult = $this->ga4Service->testConnection($googleAccount, $propertyId);
            $propertyName = $request->input('property_name') ?: ($testResult['property_name'] ?? null);
            $timeZone = $testResult['time_zone'] ?: ($request->input('time_zone') ?? 'UTC');
            $currencyCode = $testResult['currency_code'] ?: ($request->input('currency_code') ?? 'USD');
            $accountId = $request->input('account_id') ?: ($testResult['account_id'] ?? null);
            $formattedPropertyId = $testResult['property_id'] ?? $propertyId;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xác thực quyền truy cập GA4 Property này: '.$e->getMessage(),
            ], 422);
        }

        $ga4 = DB::transaction(function () use ($website, $workspaceId, $googleAccount, $formattedPropertyId, $propertyName, $accountId, $timeZone, $currencyCode, $request) {
            return WebsiteGoogleAnalytics::withoutGlobalScopes()->updateOrCreate(
                ['website_id' => $website->id],
                [
                    'workspace_id' => $workspaceId,
                    'google_account_id' => $googleAccount->id,
                    'property_id' => $formattedPropertyId,
                    'property_name' => $propertyName,
                    'account_id' => $accountId,
                    'account_name' => $request->input('account_name'),
                    'measurement_id' => $request->input('measurement_id'),
                    'time_zone' => $timeZone,
                    'currency_code' => $currencyCode,
                    'is_verified' => true,
                    'sync_status' => 'idle',
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Kết nối Google Analytics 4 thành công.',
            'data' => $ga4->load('googleAccount'),
        ], 200);
    }

    /**
     * Disconnect GA4 property from the website.
     */
    public function disconnect(Request $request, int $id): JsonResponse
    {
        $website = $this->getAuthorizedWebsite($request, $id);

        $ga4 = WebsiteGoogleAnalytics::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->first();

        if ($ga4) {
            $ga4->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã ngắt kết nối Google Analytics 4 thành công.',
        ]);
    }

    /**
     * Test connection and verify credentials & timezone for the connected GA4 property.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $website = $this->getAuthorizedWebsite($request, $id);

        $ga4 = WebsiteGoogleAnalytics::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->firstOrFail();

        $googleAccount = GoogleAccount::withoutGlobalScopes()
            ->where('id', $ga4->google_account_id)
            ->firstOrFail();

        try {
            $result = $this->ga4Service->testConnection($googleAccount, $ga4->property_id);
            $ga4->update([
                'is_verified' => true,
                'property_name' => $result['property_name'] ?? $ga4->property_name,
                'time_zone' => $result['time_zone'] ?? $ga4->time_zone,
                'currency_code' => $result['currency_code'] ?? $ga4->currency_code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kiểm tra kết nối Google Analytics 4 thành công.',
                'data' => array_merge($result, [
                    'last_synced_at' => $ga4->last_synced_at?->toIso8601String(),
                    'sync_status' => $ga4->sync_status,
                ]),
            ]);
        } catch (\Throwable $e) {
            $ga4->update(['is_verified' => false]);

            return response()->json([
                'success' => false,
                'message' => 'Xác thực Google Analytics 4 thất bại: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Query live Google Analytics 4 report metrics for the website.
     */
    public function query(Request $request, int $id): JsonResponse
    {
        $website = $this->getAuthorizedWebsite($request, $id);

        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'dimensions' => 'nullable|array',
            'dimensions.*' => 'string',
            'metrics' => 'nullable|array',
            'metrics.*' => 'string',
            'row_limit' => 'nullable|integer|min:1|max:100000',
            'offset' => 'nullable|integer|min:0',
        ]);

        $ga4 = WebsiteGoogleAnalytics::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->firstOrFail();

        $googleAccount = GoogleAccount::withoutGlobalScopes()
            ->where('id', $ga4->google_account_id)
            ->firstOrFail();

        $dimensions = $validated['dimensions'] ?? ['date', 'landingPagePlusQueryString'];
        $metrics = $validated['metrics'] ?? ['sessions', 'totalUsers', 'screenPageViews', 'engagementRate', 'conversions'];
        $limit = (int) ($validated['row_limit'] ?? 1000);
        $offset = (int) ($validated['offset'] ?? 0);

        try {
            $metricDTOs = $this->ga4Service->runReport(
                account: $googleAccount,
                propertyId: $ga4->property_id,
                startDate: $validated['start_date'],
                endDate: $validated['end_date'],
                dimensions: $dimensions,
                metrics: $metrics,
                limit: $limit,
                offset: $offset
            );

            return response()->json([
                'success' => true,
                'total_rows' => count($metricDTOs),
                'time_zone' => $ga4->time_zone,
                'currency_code' => $ga4->currency_code,
                'data' => array_map(fn ($m) => $m->toArray(), $metricDTOs),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi truy vấn Google Analytics 4: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Resolve website ensuring tenant and workspace authorization.
     */
    protected function getAuthorizedWebsite(Request $request, int $id): Website
    {
        $workspaceId = BelongsToWorkspace::resolveCurrentWorkspaceId();

        $query = Website::withoutGlobalScopes()->where('id', $id);

        if ($workspaceId !== null) {
            $query->where('workspace_id', $workspaceId);
        }

        return $query->firstOrFail();
    }
}
