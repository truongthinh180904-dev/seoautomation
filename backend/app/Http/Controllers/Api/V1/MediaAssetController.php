<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaAssetResource;
use App\Jobs\Media\DownloadImageJob;
use App\Jobs\Media\UploadToWordPressMediaJob;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaAssetController extends Controller
{
    public function index(Request $request)
    {
        $query = MediaAsset::query()
            ->where('tenant_id', $request->user()->tenant_id)
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
        $asset = $this->findForTenant($id, $request->user()->tenant_id);
        $asset->update(['status' => 'pending', 'error_message' => null]);
        DownloadImageJob::dispatch($asset->id)->onQueue('imports');

        return response()->json(['message' => 'Media download queued.']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'article_id' => ['nullable', 'integer', 'exists:articles,id'],
            'source_type' => ['required', Rule::in(['external_url', 'ai_generated', 'wordpress_existing'])],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'wordpress_media_id' => ['nullable', 'integer'],
            'wordpress_media_url' => ['nullable', 'url', 'max:2000'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $asset = MediaAsset::create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id,
            'status' => $validated['source_type'] === 'external_url' ? 'pending' : 'uploaded',
            'metadata' => array_merge($validated['metadata'] ?? [], ['role' => data_get($validated, 'metadata.role', 'inline')]),
        ]));

        if ($asset->source_type->value === 'external_url') {
            DownloadImageJob::dispatch($asset->id)->onQueue('imports');
        }

        return response()->json([
            'message' => 'Media asset saved.',
            'data' => new MediaAssetResource($asset),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $asset = $this->findForTenant($id, $request->user()->tenant_id);
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
        $validated = $request->validate([
            'wordpress_site_id' => ['required', 'integer', 'exists:wordpress_sites,id'],
        ]);

        $asset = $this->findForTenant($id, $request->user()->tenant_id);
        UploadToWordPressMediaJob::dispatch($asset->id, (int) $validated['wordpress_site_id'])->onQueue('publishing');

        return response()->json(['message' => 'WordPress media upload queued.']);
    }

    protected function findForTenant(int $id, int $tenantId): MediaAsset
    {
        return MediaAsset::where('tenant_id', $tenantId)->findOrFail($id);
    }
}
