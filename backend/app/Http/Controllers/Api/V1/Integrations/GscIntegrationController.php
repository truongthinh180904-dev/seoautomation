<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\SEO\ConnectGscRequest;
use App\Models\GoogleAccount;
use App\Models\Website;
use App\Models\WebsiteSearchConsole;
use App\Services\SEO\GSC\GoogleSearchConsoleService;
use App\Traits\BelongsToWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GscIntegrationController extends Controller
{
    public function __construct(
        protected GoogleSearchConsoleService $gscService
    ) {}

    /**
     * List all Google Search Console properties accessible by the workspace's Google account.
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
            $sites = $this->gscService->getSites($googleAccount);

            $currentGsc = WebsiteSearchConsole::withoutGlobalScopes()
                ->where('website_id', $website->id)
                ->first();

            $properties = array_map(function (array $site) use ($currentGsc) {
                $isConnected = $currentGsc !== null && $currentGsc->site_url === $site['site_url'];

                return array_merge($site, [
                    'is_connected' => $isConnected,
                ]);
            }, $sites);

            return response()->json([
                'success' => true,
                'properties' => $properties,
                'connected_property' => $currentGsc,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách Property từ Search Console: '.$e->getMessage(),
                'properties' => [],
            ], 400);
        }
    }

    /**
     * Connect a Search Console property to the website.
     */
    public function connect(ConnectGscRequest $request, int $id): JsonResponse
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

        $siteUrl = (string) $request->input('site_url');
        $propertyType = $request->input('property_type');
        if (! $propertyType) {
            $propertyType = str_starts_with($siteUrl, 'sc-domain:') ? 'domain' : 'url_prefix';
        }

        try {
            $testResult = $this->gscService->testConnection($googleAccount, $siteUrl);
            $permissionLevel = $testResult['permission_level'] ?? 'siteOwner';
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xác thực quyền truy cập Property này: '.$e->getMessage(),
            ], 422);
        }

        $gsc = DB::transaction(function () use ($website, $workspaceId, $googleAccount, $siteUrl, $propertyType, $permissionLevel) {
            return WebsiteSearchConsole::withoutGlobalScopes()->updateOrCreate(
                ['website_id' => $website->id],
                [
                    'workspace_id' => $workspaceId,
                    'google_account_id' => $googleAccount->id,
                    'site_url' => $siteUrl,
                    'property_type' => $propertyType,
                    'permission_level' => $permissionLevel,
                    'is_verified' => true,
                    'sync_status' => 'idle',
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Kết nối Google Search Console thành công.',
            'data' => $gsc->load('googleAccount'),
        ], 200);
    }

    /**
     * Disconnect Google Search Console property from the website.
     */
    public function disconnect(Request $request, int $id): JsonResponse
    {
        $website = $this->getAuthorizedWebsite($request, $id);

        $gsc = WebsiteSearchConsole::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->first();

        if ($gsc) {
            $gsc->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã ngắt kết nối Google Search Console thành công.',
        ]);
    }

    /**
     * Test connection and verify credentials for the connected Search Console property.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $website = $this->getAuthorizedWebsite($request, $id);

        $gsc = WebsiteSearchConsole::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->firstOrFail();

        $googleAccount = GoogleAccount::withoutGlobalScopes()
            ->where('id', $gsc->google_account_id)
            ->firstOrFail();

        try {
            $result = $this->gscService->testConnection($googleAccount, $gsc->site_url);
            $gsc->update([
                'is_verified' => true,
                'permission_level' => $result['permission_level'] ?? $gsc->permission_level,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kiểm tra kết nối Search Console thành công.',
                'data' => array_merge($result, [
                    'last_synced_at' => $gsc->last_synced_at?->toIso8601String(),
                    'sync_status' => $gsc->sync_status,
                ]),
            ]);
        } catch (\Throwable $e) {
            $gsc->update(['is_verified' => false]);

            return response()->json([
                'success' => false,
                'message' => 'Xác thực Search Console thất bại: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Query live Search Console analytics metrics for the website.
     */
    public function query(Request $request, int $id): JsonResponse
    {
        $website = $this->getAuthorizedWebsite($request, $id);

        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'dimensions' => 'nullable|array',
            'dimensions.*' => 'string|in:date,query,page,country,device',
            'row_limit' => 'nullable|integer|min:1|max:25000',
            'start_row' => 'nullable|integer|min:0',
        ]);

        $gsc = WebsiteSearchConsole::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->firstOrFail();

        $googleAccount = GoogleAccount::withoutGlobalScopes()
            ->where('id', $gsc->google_account_id)
            ->firstOrFail();

        $dimensions = $validated['dimensions'] ?? ['query', 'page', 'date', 'device', 'country'];
        $rowLimit = (int) ($validated['row_limit'] ?? 1000);
        $startRow = (int) ($validated['start_row'] ?? 0);

        try {
            $metrics = $this->gscService->querySearchAnalytics(
                account: $googleAccount,
                siteUrl: $gsc->site_url,
                startDate: $validated['start_date'],
                endDate: $validated['end_date'],
                dimensions: $dimensions,
                rowLimit: $rowLimit,
                startRow: $startRow
            );

            return response()->json([
                'success' => true,
                'total_rows' => count($metrics),
                'data' => array_map(fn ($m) => $m->toArray(), $metrics),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi truy vấn Search Console Analytics: '.$e->getMessage(),
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
