<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KeywordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportKeywordsRequest;
use App\Http\Requests\StoreKeywordRequest;
use App\Http\Requests\UpdateKeywordRequest;
use App\Http\Resources\KeywordResource;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Services\Keyword\KeywordImportService;
use App\Services\Keyword\KeywordParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;

class KeywordController extends Controller
{
    public function __construct(
        protected KeywordRepositoryInterface $repository,
        protected KeywordParserService $parserService,
        protected KeywordImportService $importService
    ) {}

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $filters = $request->only(['status', 'wordpress_site_id', 'search', 'batch_id']);
        $perPage = $request->input('per_page', 20);

        $keywords = $this->repository->paginateForTenant($tenantId, $filters, $perPage);
        return KeywordResource::collection($keywords);
    }

    public function store(StoreKeywordRequest $request)
    {
        $data = $request->validated();
        $keyword = $this->repository->create($data);
        return new KeywordResource($keyword);
    }

    public function import(ImportKeywordsRequest $request)
    {
        \Illuminate\Support\Facades\Log::info('Keyword Import Started', [
            'count' => count($request->input('keywords', [])),
            'first_row' => $request->input('keywords.0'),
        ]);

        try {
            $summary = $this->importService->import(
                $request->input('keywords'), 
                $request->user()->tenant_id, 
                $request->user()->id
            );
            
            return response()->json($summary);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(UpdateKeywordRequest $request, int $id)
    {
        $keyword = $this->repository->findById($id);
        if (!$keyword) abort(404);
        $keyword->update($request->validated());
        return new KeywordResource($keyword);
    }

    public function destroy(int $id)
    {
        $keyword = $this->repository->findById($id);
        if (!$keyword) abort(404);
        
        $keyword->update(['status' => KeywordStatus::SKIPPED]);
        return response()->noContent();
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        \App\Models\Keyword::whereIn('id', $ids)->update(['status' => KeywordStatus::SKIPPED]);
        return response()->noContent();
    }
}
