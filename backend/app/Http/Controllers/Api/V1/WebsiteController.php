<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWebsiteRequest;
use App\Http\Requests\Workspace\UpdateWebsiteRequest;
use App\Http\Resources\WebsiteResource;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Workspace\WebsiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function __construct(
        protected WebsiteService $websiteService
    ) {}

    public function index(Request $request)
    {
        $workspace = $this->resolveWorkspace($request);
        $websites = $this->websiteService->getWebsitesForWorkspace($workspace);

        return WebsiteResource::collection($websites);
    }

    public function store(StoreWebsiteRequest $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $website = $this->websiteService->createWebsite($workspace, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Thêm Website thành công.',
            'data' => new WebsiteResource($website->load('wordpressSite')),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $website = Website::where('workspace_id', $workspace->id)->with('wordpressSite')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new WebsiteResource($website),
        ]);
    }

    public function update(UpdateWebsiteRequest $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $website = Website::where('workspace_id', $workspace->id)->findOrFail($id);
        $updatedWebsite = $this->websiteService->updateWebsite($website, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật Website thành công.',
            'data' => new WebsiteResource($updatedWebsite->load('wordpressSite')),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $website = Website::where('workspace_id', $workspace->id)->findOrFail($id);

        $this->websiteService->deleteWebsite($website);

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa Website thành công.',
        ]);
    }

    protected function resolveWorkspace(Request $request): Workspace
    {
        $workspace = $request->attributes->get('current_workspace');
        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        $workspaceId = $request->header('X-Workspace-Id')
            ?: $request->query('workspace_id')
            ?: $request->user()->default_workspace_id;

        if ($workspaceId) {
            $found = Workspace::find((int) $workspaceId);
            if ($found) {
                $user = $request->user();
                $isSuperAdmin = ($user->is_platform_admin || $user->role === \App\Enums\UserRole::SUPER_ADMIN);
                if ($isSuperAdmin || $found->members()->where('user_id', $user->id)->where('is_active', true)->exists()) {
                    return $found;
                }
            }
        }

        $fallback = $request->user()->workspaces()->first();
        if ($fallback) {
            return $fallback;
        }

        abort(404, 'Không tìm thấy không gian làm việc.');
    }
}
