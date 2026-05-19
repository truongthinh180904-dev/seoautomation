<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use App\Services\Campaign\CampaignService;
use App\Services\Campaign\CampaignStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignRepositoryInterface $campaigns,
        protected CampaignService $campaignService,
        protected CampaignStatsService $statsService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'wordpress_site_id', 'search']);
        $perPage = min($request->integer('per_page', 20), 100);

        return CampaignResource::collection(
            $this->campaigns->paginateForTenant($request->user()->tenant_id, $filters, $perPage)
        );
    }

    public function store(StoreCampaignRequest $request)
    {
        $campaign = $this->campaignService->create(
            $request->validated(),
            $request->user()->tenant_id,
            $request->user()->id
        );

        return new CampaignResource($campaign->load(['wordpressSite:id,name,url', 'creator:id,name']));
    }

    public function show(Request $request, int $id)
    {
        $campaign = $this->campaigns->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$campaign) abort(404);

        return new CampaignResource($campaign);
    }

    public function update(UpdateCampaignRequest $request, int $id)
    {
        $campaign = $this->campaigns->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$campaign) abort(404);

        return new CampaignResource($this->campaignService->update($campaign, $request->validated()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->campaigns->deleteForTenant($id, $request->user()->tenant_id)) {
            abort(404);
        }

        return response()->json(null, 204);
    }

    public function stats(Request $request, int $id): JsonResponse
    {
        $campaign = $this->campaigns->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$campaign) abort(404);

        return response()->json($this->statsService->getStats($campaign));
    }

    public function start(Request $request, int $id)
    {
        return $this->transition($request, $id, CampaignStatus::PROCESSING);
    }

    public function pause(Request $request, int $id)
    {
        return $this->transition($request, $id, CampaignStatus::PAUSED);
    }

    public function resume(Request $request, int $id)
    {
        return $this->transition($request, $id, CampaignStatus::PROCESSING);
    }

    protected function transition(Request $request, int $id, CampaignStatus $status): CampaignResource
    {
        $campaign = $this->campaigns->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$campaign) abort(404);

        return new CampaignResource($this->campaignService->setStatus($campaign, $status));
    }
}
