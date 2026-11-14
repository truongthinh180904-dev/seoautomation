<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KeywordStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDestroyKeywordsRequest;
use App\Http\Requests\ImportKeywordsRequest;
use App\Http\Requests\StoreKeywordRequest;
use App\Http\Requests\UpdateKeywordRequest;
use App\Http\Resources\KeywordResource;
use App\Jobs\SERP\SerpResearchJob;
use App\Models\Workspace;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Services\Billing\CreditLedgerService;
use App\Services\Cost\CostTrackingService;
use App\Services\Keyword\KeywordImportPreviewService;
use App\Services\Keyword\KeywordImportService;
use App\Services\Keyword\KeywordParserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeywordController extends Controller
{
    public function __construct(
        protected KeywordRepositoryInterface $repository,
        protected KeywordParserService $parserService,
        protected KeywordImportService $importService,
        protected KeywordImportPreviewService $previewService
    ) {}

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $filters = $request->only(['status', 'wordpress_site_id', 'campaign_id', 'search', 'batch_id']);
        $perPage = $request->input('per_page', 20);

        $keywords = $this->repository->paginateForTenant($tenantId, $filters, $perPage);

        return KeywordResource::collection($keywords);
    }

    public function store(StoreKeywordRequest $request)
    {
        $workspace = $request->attributes->get('current_workspace');
        $workspaceId = $workspace?->id ?: $request->user()->default_workspace_id;

        $data = array_merge($request->validated(), [
            'tenant_id' => $request->user()->tenant_id,
            'workspace_id' => $workspaceId,
            'user_id' => $request->user()->id,
        ]);
        $keyword = $this->repository->create($data);

        return new KeywordResource($keyword);
    }

    public function serp(Request $request, int $id): JsonResponse
    {
        $keyword = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (! $keyword) {
            abort(404);
        }

        $currentWorkspace = $request->attributes->get('current_workspace');
        if ($currentWorkspace && $keyword->workspace_id && $keyword->workspace_id !== $currentWorkspace->id) {
            abort(404);
        }

        $workspace = $currentWorkspace
            ?: ($keyword->workspace_id ? Workspace::where('id', $keyword->workspace_id)->where('legacy_tenant_id', $request->user()->tenant_id)->first() : null)
            ?: ($request->user()->default_workspace_id ? Workspace::where('id', $request->user()->default_workspace_id)->where('legacy_tenant_id', $request->user()->tenant_id)->first() : null);

        $quota = $workspace ? app(CostTrackingService::class)->checkWorkspaceQuota($workspace) : app(CostTrackingService::class)->checkQuota($request->user()->tenant_id);
        if (! $quota->canProceed) {
            return response()->json([
                'message' => $quota->message ?? 'AI quota exceeded.',
                'quota' => $quota->toArray(),
            ], 402);
        }

        $creditCost = (int) config('ai_costs.credits.standard_article', 20);
        $targetWorkspaceId = $workspace?->id ?: $keyword->workspace_id;
        if ($targetWorkspaceId && app()->bound(CreditLedgerService::class)) {
            try {
                app(CreditLedgerService::class)->checkCredits($targetWorkspaceId, $creditCost);
            } catch (InsufficientCreditsException $e) {
                return response()->json([
                    'message' => 'Số dư credit không đủ để nghiên cứu SERP.',
                    'required' => $creditCost,
                ], 402);
            }
        }

        DB::transaction(function () use ($keyword) {
            $keyword->update(['status' => KeywordStatus::PROCESSING]);
        });

        SerpResearchJob::dispatch($keyword);

        return response()->json([
            'success' => true,
            'message' => 'Yêu cầu nghiên cứu SERP đã được đưa vào hàng đợi.',
            'data' => new KeywordResource($keyword->fresh()),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $keyword = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (! $keyword) {
            abort(404);
        }

        return new KeywordResource($keyword->load([
            'campaign:id,name,status',
            'wordpressSite:id,name',
            'article:id,keyword_id,status,review_notes,pipeline_status',
        ]));
    }

    public function import(ImportKeywordsRequest $request)
    {
        try {
            $rows = $this->parserService->parseUploadedFile($request->file('file'));

            $workspace = $request->attributes->get('current_workspace');
            $workspaceId = $workspace?->id ?: $request->user()->default_workspace_id;

            $summary = $this->importService->import(
                $rows,
                $request->user()->tenant_id,
                $request->user()->id,
                $request->integer('campaign_id') ?: null,
                $workspaceId
            );

            return response()->json($summary);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Import keywords failed.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function importPreview(ImportKeywordsRequest $request)
    {
        try {
            $rows = $this->parserService->parseUploadedFile($request->file('file'));

            return response()->json($this->previewService->preview(
                $rows,
                $request->user()->tenant_id,
                $request->integer('campaign_id') ?: null
            ));
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Preview import failed.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(UpdateKeywordRequest $request, int $id)
    {
        $keyword = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (! $keyword) {
            abort(404);
        }
        $payload = $request->validated();

        if (array_key_exists('meta', $payload)) {
            $payload['meta'] = array_merge($keyword->meta ?? [], $payload['meta'] ?? []);
        }

        $keyword->update($payload);

        return new KeywordResource($keyword->fresh()->load([
            'campaign:id,name,status',
            'wordpressSite:id,name',
            'article:id,keyword_id,status,review_notes,pipeline_status',
        ]));
    }

    public function destroy(Request $request, int $id)
    {
        $keyword = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (! $keyword) {
            abort(404);
        }

        $keyword->update(['status' => KeywordStatus::SKIPPED]);

        return response()->noContent();
    }

    public function bulkDestroy(BulkDestroyKeywordsRequest $request)
    {
        $validated = $request->validated();

        $this->repository->bulkMarkSkippedForTenant(
            $request->user()->tenant_id,
            $validated['ids']
        );

        return response()->noContent();
    }
}
