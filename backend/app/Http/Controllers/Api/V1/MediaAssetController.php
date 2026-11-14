<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaSourceType;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaAssetResource;
use App\Jobs\Media\DownloadImageJob;
use App\Jobs\Media\UploadToWordPressMediaJob;
use App\Models\MediaAsset;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaAssetController extends Controller
{
    public function index(Request $request)
    {
        [$workspaceId, $tenantId] = $this->resolveScope($request);

        $query = MediaAsset::query()
            ->where(function ($q) use ($workspaceId, $tenantId) {
                if ($workspaceId) {
                    $q->where('workspace_id', $workspaceId);
                }
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
                if (! $workspaceId && ! $tenantId) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->with(['campaign:id,name', 'article:id,title']);

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('article_id')) {
            $query->where('article_id', $request->integer('article_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return MediaAssetResource::collection(
            $query->latest()->paginate(min($request->integer('per_page', 24), 100))
        );
    }

    public function retryDownload(Request $request, int $id): JsonResponse
    {
        $asset = $this->findForTenant($id, $request);
        $asset->update(['status' => 'pending', 'error_message' => null]);
        DownloadImageJob::dispatch($asset->id)->onQueue('imports');

        return response()->json(['message' => 'Media download queued.']);
    }

    public function store(Request $request): JsonResponse
    {
        [$workspaceId, $tenantId] = $this->resolveScope($request);

        $tenantOrWorkspaceCondition = function ($query) use ($workspaceId, $tenantId) {
            $query->where(function ($q) use ($workspaceId, $tenantId) {
                if ($workspaceId) {
                    $q->where('workspace_id', $workspaceId);
                }
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
                if (! $workspaceId && ! $tenantId) {
                    $q->whereRaw('1 = 0');
                }
            });
        };

        $validated = $request->validate([
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where($tenantOrWorkspaceCondition)],
            'article_id' => ['nullable', 'integer', Rule::exists('articles', 'id')->where($tenantOrWorkspaceCondition)],
            'source_type' => ['required', Rule::enum(MediaSourceType::class)],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'wordpress_media_id' => ['nullable', 'integer'],
            'wordpress_media_url' => ['nullable', 'url', 'max:2000'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $asset = MediaAsset::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'workspace_id' => $workspaceId,
            'status' => $validated['source_type'] === 'external_url' ? 'pending' : 'uploaded',
            'metadata' => array_merge($validated['metadata'] ?? [], ['role' => data_get($validated, 'metadata.role', 'inline')]),
        ]));

        if ($asset->source_type === MediaSourceType::EXTERNAL_URL || $asset->source_type === 'external_url') {
            DownloadImageJob::dispatch($asset->id)->onQueue('imports');
        }

        return response()->json([
            'message' => 'Media asset saved.',
            'data' => new MediaAssetResource($asset),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $asset = $this->findForTenant($id, $request);
        $validated = $request->validate([
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'caption' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('metadata', $validated)) {
            $validated['metadata'] = array_merge($asset->metadata ?? [], $validated['metadata'] ?? []);
        }

        $asset->update($validated);

        return response()->json([
            'message' => 'Media asset updated.',
            'data' => new MediaAssetResource($asset->fresh()),
        ]);
    }

    public function uploadToWordPress(Request $request, int $id): JsonResponse
    {
        [$workspaceId, $tenantId] = $this->resolveScope($request);

        $validated = $request->validate([
            'wordpress_site_id' => [
                'required',
                'integer',
                Rule::exists('wordpress_sites', 'id')->where(function ($query) use ($workspaceId, $tenantId) {
                    $query->where(function ($q) use ($workspaceId, $tenantId) {
                        if ($workspaceId) {
                            $q->where('workspace_id', $workspaceId);
                        }
                        if ($tenantId) {
                            $q->orWhere('tenant_id', $tenantId);
                        }
                        if (! $workspaceId && ! $tenantId) {
                            $q->whereRaw('1 = 0');
                        }
                    });
                }),
            ],
        ]);

        $asset = $this->findForTenant($id, $request);
        UploadToWordPressMediaJob::dispatch($asset->id, (int) $validated['wordpress_site_id'])->onQueue('publishing');

        return response()->json(['message' => 'WordPress media upload queued.']);
    }

    protected function findForTenant(int $id, Request $request): MediaAsset
    {
        [$workspaceId, $tenantId] = $this->resolveScope($request);

        return MediaAsset::where(function ($q) use ($workspaceId, $tenantId) {
            if ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            }
            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId);
            }
            if (! $workspaceId && ! $tenantId) {
                $q->whereRaw('1 = 0');
            }
        })->findOrFail($id);
    }

    protected function resolveScope(Request $request): array
    {
        $user = $request->user();
        $workspace = $request->attributes->get('current_workspace');
        if (! $workspace && $user) {
            $workspaceId = (int) ($request->header('X-Workspace-Id') ?: $request->query('workspace_id') ?: $user->default_workspace_id);
            if ($workspaceId) {
                $workspace = Workspace::find($workspaceId);
            }
        }
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $user?->default_workspace_id;
        $tenantId = $workspace instanceof Workspace ? $workspace->legacy_tenant_id : $user?->tenant_id;

        return [$workspaceId, $tenantId];
    }
}
