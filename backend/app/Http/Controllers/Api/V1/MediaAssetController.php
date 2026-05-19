<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaAssetResource;
use App\Jobs\Media\DownloadImageJob;
use App\Jobs\Media\UploadToWordPressMediaJob;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
