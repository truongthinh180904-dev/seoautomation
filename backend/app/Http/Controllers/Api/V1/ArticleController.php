<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Events\ArticleApproved;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateArticleRequest;
use App\Http\Requests\ReviewArticleActionRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Jobs\AI\GenerateOutlineJob;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function __construct(
        protected ArticleRepositoryInterface $repository,
        protected KeywordRepositoryInterface $keywords
    ) {}

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $filters = $request->only(['status', 'keyword_id', 'wordpress_site_id', 'wp_site_id', 'search']);
        $perPage = min($request->integer('per_page', 20), 100);
        $articles = $this->repository->paginateForTenant($tenantId, $filters, $perPage);

        return ArticleResource::collection($articles);
    }

    public function show(int $id, Request $request)
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$article) abort(404);

        return new ArticleResource($article);
    }

    public function update(int $id, UpdateArticleRequest $request)
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$article) abort(404);

        $this->repository->update($id, $request->validated());
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);

        return (new ArticleResource($article))->additional([
            'message' => 'Article updated successfully.',
        ]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        if (!$this->repository->deleteForTenant($id, $request->user()->tenant_id)) {
            abort(404);
        }

        return response()->json(null, 204);
    }

    public function retry(int $id, Request $request): JsonResponse
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$article || $article->status !== ArticleStatus::FAILED) {
            abort(404);
        }

        $this->repository->updateStatus($article->id, ArticleStatus::APPROVED);

        if ($article->wordpress_site_id) {
            PublishToWordPressJob::dispatch($article->id, $article->wordpress_site_id);
        }

        return response()->json(['message' => 'Article queued for retry.']);
    }

    /**
     * Public endpoint — accessible via review token (no auth required).
     */
    public function reviewByToken(string $token): JsonResponse
    {
        $article = $this->repository->findReviewableByToken($token);
        if (!$article) abort(404);

        return new ArticleResource($article);
    }

    /**
     * Approve or reject an article via review token (no auth required).
     */
    public function reviewAction(string $token, ReviewArticleActionRequest $request): JsonResponse
    {
        $article = $this->repository->findPendingReviewByToken($token);
        if (!$article) abort(404);

        if ($request->action === 'approve') {
            $this->repository->updateStatus($article->id, ArticleStatus::APPROVED);
            $article->refresh();
            ArticleApproved::dispatch($article);
        } else {
            $this->repository->update($article->id, [
                'status' => ArticleStatus::REJECTED,
                'rejection_reason' => $request->reason,
            ]);
        }

        return response()->json(['message' => 'Review action recorded.']);
    }

     /**
     * Generate a new article from a keyword (triggers full AI pipeline).
     */
    public function generate(GenerateArticleRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validated();

        $keyword = $this->keywords->findByIdForTenant($validated['keyword_id'], $tenantId);

        if (!$keyword) {
            abort(404);
        }

        $article = $this->repository->findByKeywordIdForTenant($keyword->id, $tenantId);

        if (!$article) {
            $article = $this->repository->create([
                'tenant_id' => $tenantId,
                'keyword_id' => $keyword->id,
                'wordpress_site_id' => $validated['wordpress_site_id'] ?? $keyword->wordpress_site_id,
                'user_id' => $request->user()->id,
                'title' => 'Bài viết: ' . $keyword->keyword,
                'slug' => Str::slug($keyword->keyword),
                'focus_keyword' => $keyword->keyword,
                'status' => ArticleStatus::DRAFT,
            ]);
        }

        GenerateOutlineJob::dispatch($keyword->id, $article->id)->onQueue('ai-writing');
        $keyword->update(['status' => KeywordStatus::PROCESSING]);

        return response()->json([
            'message' => 'AI article generation queued.',
            'article' => new ArticleResource($article->fresh(['keyword', 'wordpressSite', 'user'])),
        ], 202);
    }
}
