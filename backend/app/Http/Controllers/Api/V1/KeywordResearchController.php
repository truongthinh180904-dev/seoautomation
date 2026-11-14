<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\KeywordResearchRequest;
use App\Models\Workspace;
use App\Services\SEO\KeywordResearchService;
use Illuminate\Http\JsonResponse;

class KeywordResearchController extends Controller
{
    public function __construct(
        protected KeywordResearchService $keywordResearchService
    ) {}

    /**
     * GET/POST /api/v1/keywords/research
     * Khám phá từ khóa hạt giống và phân tích ý định tìm kiếm.
     */
    public function research(KeywordResearchRequest $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        $query = (string) ($request->input('query') ?: $request->input('keyword'));
        $options = [
            'language' => $request->input('language', 'vi'),
            'country' => $request->input('country', 'vn'),
            'limit' => (int) $request->input('limit', 50),
            'website_id' => $request->input('website_id'),
            'campaign_id' => $request->input('campaign_id'),
        ];

        $result = $this->keywordResearchService->research(
            workspace: $workspace,
            query: $query,
            options: $options,
            actor: $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Nghiên cứu từ khóa thành công.',
            'data' => $result,
        ]);
    }

    /**
     * POST /api/v1/keywords/cluster
     * Gom cụm danh sách từ khóa theo chủ đề và ý định tìm kiếm.
     */
    public function cluster(KeywordResearchRequest $request): JsonResponse
    {
        $workspace = null;
        try {
            $workspace = $this->resolveWorkspace($request);
        } catch (\Throwable) {
            // Workspace có thể là tùy chọn cho cluster
        }

        $keywords = $request->input('keywords', []);
        $options = [
            'language' => $request->input('language', 'vi'),
            'workspace_id' => $workspace?->id,
            'tenant_id' => $workspace?->legacy_tenant_id ?? (int) ($request->user()?->tenant_id ?? 0),
        ];

        $result = $this->keywordResearchService->cluster($keywords, $options);

        return response()->json([
            'success' => true,
            'message' => 'Gom cụm từ khóa thành công.',
            'data' => $result,
        ]);
    }

    /**
     * POST /api/v1/keywords/research/save
     * Lưu danh sách từ khóa vào chiến dịch và không gian làm việc.
     */
    public function saveToCampaign(KeywordResearchRequest $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        $keywords = $request->input('keywords', []);
        $campaignId = $request->input('campaign_id') ? (int) $request->input('campaign_id') : null;
        $websiteId = $request->input('website_id') ? (int) $request->input('website_id') : null;

        $result = $this->keywordResearchService->saveResearchedKeywords(
            workspace: $workspace,
            keywords: $keywords,
            campaignId: $campaignId,
            websiteId: $websiteId,
            actor: $user
        );

        return response()->json([
            'success' => true,
            'message' => sprintf('Đã lưu thành công %d từ khóa vào chiến dịch.', $result['saved_count']),
            'data' => $result,
        ]);
    }

    /**
     * Trích xuất Workspace hiện tại từ Request context hoặc Header.
     */
    protected function resolveWorkspace(KeywordResearchRequest $request): Workspace
    {
        /** @var Workspace|null $workspace */
        $workspace = $request->attributes->get('current_workspace');

        if (! $workspace) {
            $workspaceId = $request->header('X-Workspace-Id')
                ?: $request->query('workspace_id')
                ?: $request->user()?->default_workspace_id;

            if ($workspaceId) {
                $workspace = Workspace::find((int) $workspaceId);
            }
        }

        if (! $workspace && $request->user()) {
            $workspace = $request->user()->workspaces()->wherePivot('is_active', true)->first();
        }

        if (! $workspace) {
            abort(404, 'Không tìm thấy Không gian làm việc (Workspace) hợp lệ.');
        }

        return $workspace;
    }
}
