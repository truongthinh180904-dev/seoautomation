<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KeywordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDestroyKeywordsRequest;
use App\Http\Requests\ImportKeywordsRequest;
use App\Http\Requests\StoreKeywordRequest;
use App\Http\Requests\UpdateKeywordRequest;
use App\Http\Resources\KeywordResource;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Services\Keyword\KeywordImportService;
use App\Services\Keyword\KeywordImportPreviewService;
use App\Services\Keyword\KeywordParserService;
use Illuminate\Http\Request;
use Exception;

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
        $data = array_merge($request->validated(), [
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
        ]);
        $keyword = $this->repository->create($data);
        return new KeywordResource($keyword);
    }

    public function import(ImportKeywordsRequest $request)
    {
        try {
            $rows = $this->parserService->parseUploadedFile($request->file('file'));

            $summary = $this->importService->import(
                $rows,
                $request->user()->tenant_id, 
                $request->user()->id,
                $request->integer('campaign_id') ?: null
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
        if (!$keyword) abort(404);
        $keyword->update($request->validated());
        return new KeywordResource($keyword);
    }

    public function destroy(Request $request, int $id)
    {
        $keyword = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$keyword) abort(404);
        
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
