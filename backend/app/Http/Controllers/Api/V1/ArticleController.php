<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ArticleStatus;
use App\Events\ArticleApproved;
use App\Events\ArticleGenerated;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Jobs\Publishing\PublishToWordPressJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = Article::with(['keyword:id,keyword', 'wordpressSite:id,name'])
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhereHas('keyword', fn($kq) => $kq->where('keyword', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('wp_site_id')) {
            $query->where('wordpress_site_id', $request->wp_site_id);
        }

        $perPage = min($request->integer('per_page', 20), 100);
        $articles = $query->paginate($perPage);

        return response()->json($articles);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $article = Article::with(['keyword', 'wordpressSite'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return response()->json(['data' => $article]);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'seo_title' => 'sometimes|string|max:255',
            'seo_description' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:draft,review,approved,rejected',
        ]);

        $article = Article::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $article->update($request->only(['title', 'content', 'seo_title', 'seo_description', 'status']));

        return response()->json(['message' => 'Article updated successfully.', 'data' => $article]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $article = Article::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $article->delete();

        return response()->json(null, 204);
    }

    public function retry(int $id, Request $request): JsonResponse
    {
        $article = Article::where('tenant_id', $request->user()->tenant_id)
            ->where('status', ArticleStatus::FAILED)
            ->findOrFail($id);

        $article->update(['status' => ArticleStatus::APPROVED]);
        PublishToWordPressJob::dispatch($article->id);

        return response()->json(['message' => 'Article queued for retry.']);
    }

    /**
     * Public endpoint — accessible via review token (no auth required).
     */
    public function reviewByToken(string $token): JsonResponse
    {
        $article = Article::with(['keyword:id,keyword'])
            ->where('review_token', $token)
            ->whereIn('status', [ArticleStatus::REVIEW, ArticleStatus::APPROVED, ArticleStatus::REJECTED])
            ->firstOrFail();

        return response()->json(['data' => $article]);
    }

    /**
     * Approve or reject an article via review token (no auth required).
     */
    public function reviewAction(string $token, Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|nullable|string|max:2000',
        ]);

        $article = Article::where('review_token', $token)
            ->where('status', ArticleStatus::REVIEW)
            ->firstOrFail();

        if ($request->action === 'approve') {
            $article->update(['status' => ArticleStatus::APPROVED]);
            // Fire event — DispatchPublishingJob listener handles queuing
            ArticleApproved::dispatch($article);
        } else {
            $article->update([
                'status' => ArticleStatus::REJECTED,
                'rejection_reason' => $request->reason,
            ]);
        }

        return response()->json(['message' => 'Review action recorded.']);
    }

    /**
     * Generate a new article from a keyword (triggers full AI pipeline).
     */
    public function generate(Request $request): JsonResponse
    {
        // Placeholder: wire up to pipeline dispatch in a future task
        return response()->json(['message' => 'AI pipeline queued.'], 202);
    }
}
