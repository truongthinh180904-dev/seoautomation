<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWordPressSiteRequest;
use App\Http\Requests\UpdateWordPressSiteRequest;
use App\Http\Resources\WordPressSiteResource;
use App\Models\WordPressSite;
use App\Repositories\Contracts\WordPressSiteRepositoryInterface;
use App\Services\WordPress\WordPressSiteService;
use Illuminate\Http\Request;

class WordPressSiteController extends Controller
{
    public function __construct(
        protected WordPressSiteRepositoryInterface $repository,
        protected WordPressSiteService $service
    ) {}

    public function index(Request $request)
    {
        $sites = WordPressSite::paginate(20);
        return WordPressSiteResource::collection($sites);
    }

    public function store(StoreWordPressSiteRequest $request)
    {
        $site = $this->repository->create($request->validated());
        return new WordPressSiteResource($site);
    }

    public function show(int $id)
    {
        $site = $this->repository->findById($id);
        if (!$site) abort(404);
        return new WordPressSiteResource($site);
    }

    public function update(UpdateWordPressSiteRequest $request, int $id)
    {
        $this->repository->update($id, $request->validated());
        return new WordPressSiteResource($this->repository->findById($id));
    }

    public function destroy(int $id)
    {
        $this->repository->delete($id);
        return response()->noContent();
    }

    public function test(int $id)
    {
        $result = $this->service->testConnection($id);
        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
