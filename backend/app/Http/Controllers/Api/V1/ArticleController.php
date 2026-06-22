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
use App\Jobs\Media\GenerateArticleImagesJob;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Services\Cost\CostTrackingService;
use App\Models\MediaAsset;
use App\Services\Article\ArticlePipelineService;
use App\Services\Article\ArticleContentFormatter;
use App\Services\Media\MediaParserService;
use App\Services\SEO\SEOAutoFixService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function __construct(
        protected ArticleRepositoryInterface $repository,
        protected KeywordRepositoryInterface $keywords,
        protected CostTrackingService $costTracking,
        protected ArticlePipelineService $pipeline,
        protected MediaParserService $mediaParser,
        protected ArticleContentFormatter $formatter,
        protected SEOAutoFixService $autoFixService
    ) {}

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $filters = $request->only(['status', 'keyword_id', 'campaign_id', 'wordpress_site_id', 'wp_site_id', 'search']);
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

        $wasApproved = $article->status === ArticleStatus::APPROVED;
        $payload = $request->validated();

        if (array_key_exists('content', $payload) && is_string($payload['content'])) {
            $payload['content'] = $this->formatter->normalize($payload['content'], $payload['title'] ?? $article->title);
        }

        if (array_key_exists('media_plan', $payload)) {
            $payload['media_plan'] = array_merge($article->media_plan ?? [], $payload['media_plan'] ?? []);
        }

        $this->repository->update($id, $payload);
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);

        if (($request->validated()['status'] ?? null) === ArticleStatus::APPROVED->value && !$wasApproved) {
            $article->forceFill([
                'approved_at' => now(),
                'reviewed_by' => $request->user()->id,
            ])->save();
            ArticleApproved::dispatch($article->fresh(['keyword', 'wordpressSite', 'user']));
        }

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

    public function autoFix(int $id, Request $request)
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$article) abort(404);

        return (new ArticleResource($this->autoFixService->autoFix($article)))->additional([
            'message' => 'SEO auto-fix applied.',
        ]);
    }

    public function approveAndPublish(int $id, Request $request): JsonResponse
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$article) abort(404);

        if (!$article->wordpress_site_id) {
            return response()->json([
                'message' => 'Bài viết chưa gắn WordPress site. Hãy gắn site từ keyword/campaign hoặc cấu hình lại bài trước khi publish.',
            ], 422);
        }

        if ($article->status === ArticleStatus::PUBLISHED || $article->status === ArticleStatus::PUBLISHING) {
            return response()->json([
                'message' => 'Bài viết đã hoặc đang được gửi lên WordPress.',
                'article' => new ArticleResource($article),
            ]);
        }

        if ($article->keyword) {
            $this->attachKeywordMediaToArticle($article->keyword, $article);
            $article->refresh();
        }

        $this->mediaParser->createAssetsForArticle($article);
        $article->refresh();

        $article->forceFill([
            'status' => ArticleStatus::APPROVED,
            'approved_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        ArticleApproved::dispatch($article->fresh(['keyword', 'wordpressSite', 'user']));

        return response()->json([
            'message' => 'Bài viết đã được duyệt và đưa vào queue đăng WordPress.',
            'article' => new ArticleResource($article->fresh(['keyword', 'wordpressSite', 'user'])),
        ], 202);
    }

    public function generateImages(int $id, Request $request): JsonResponse
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (!$article) abort(404);

        GenerateArticleImagesJob::dispatch($article->id)->onQueue('imports');

        return response()->json([
            'message' => 'AI image generation queued.',
            'article' => new ArticleResource($article->fresh(['keyword', 'wordpressSite', 'user'])),
        ], 202);
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
        $quota = $this->costTracking->checkQuota($tenantId);

        if (!$quota->canProceed) {
            return response()->json([
                'message' => $quota->message ?? 'AI quota exceeded.',
                'quota' => $quota->toArray(),
            ], 402);
        }

        $keyword = $this->keywords->findByIdForTenant($validated['keyword_id'], $tenantId);

        if (!$keyword) {
            abort(404);
        }

        $article = $this->repository->findByKeywordIdForTenant($keyword->id, $tenantId);

        if (!$article) {
            $keywordMeta = $keyword->meta ?? [];
            $article = $this->repository->create([
                'tenant_id' => $tenantId,
                'keyword_id' => $keyword->id,
                'campaign_id' => $validated['campaign_id'] ?? $keyword->campaign_id,
                'wordpress_site_id' => $validated['wordpress_site_id'] ?? $keyword->wordpress_site_id,
                'user_id' => $request->user()->id,
                'title' => 'Bài viết: ' . $keyword->keyword,
                'slug' => Str::slug($keyword->keyword),
                'focus_keyword' => $keyword->keyword,
                'featured_image_url' => $keywordMeta['featured_image_url'] ?? null,
                'media_plan' => array_filter([
                    'featured_image_url' => $keywordMeta['featured_image_url'] ?? null,
                    'image_urls' => $keywordMeta['image_urls'] ?? null,
                    'internal_links' => $keywordMeta['internal_links'] ?? null,
                    'image_generation_prompt' => $keywordMeta['image_generation_prompt'] ?? null,
                    'image_search_query' => $keywordMeta['image_search_query'] ?? null,
                    'image_source' => $keywordMeta['image_source'] ?? config('ai.image_generation.source_strategy', 'hybrid'),
                    'inline_image_count' => $keywordMeta['inline_image_count'] ?? config('ai.image_generation.default_inline_count', 3),
                ]),
                'wp_tag_names' => !empty($keywordMeta['wp_tag_names'])
                    ? array_values(array_filter(array_map('trim', explode(',', str_replace('|', ',', (string) $keywordMeta['wp_tag_names'])))))
                    : null,
                'wp_slug' => $keywordMeta['wp_slug'] ?? null,
                'excerpt' => $keywordMeta['wp_excerpt'] ?? null,
                'status' => ArticleStatus::DRAFT,
            ]);
        } else {
            $keywordMeta = $keyword->meta ?? [];
            $article->update([
                'status' => ArticleStatus::DRAFT,
                'review_notes' => null,
                'rejection_reason' => null,
                'media_plan' => array_merge($article->media_plan ?? [], array_filter([
                    'featured_image_url' => $keywordMeta['featured_image_url'] ?? null,
                    'image_urls' => $keywordMeta['image_urls'] ?? null,
                    'internal_links' => $keywordMeta['internal_links'] ?? null,
                    'image_generation_prompt' => $keywordMeta['image_generation_prompt'] ?? null,
                    'image_search_query' => $keywordMeta['image_search_query'] ?? null,
                    'image_source' => $keywordMeta['image_source'] ?? null,
                    'inline_image_count' => $keywordMeta['inline_image_count'] ?? null,
                ])),
            ]);
            $article->refresh();
        }

        $this->pipeline->reset($article);
        $this->pipeline->start($article, 'queued', 'Đang chờ queue worker nhận job.');

        $this->attachKeywordMediaToArticle($keyword, $article);

        GenerateOutlineJob::dispatch($keyword->id, $article->id)->onQueue('ai-writing');
        $keyword->update(['status' => KeywordStatus::PROCESSING]);

        return response()->json([
            'message' => 'AI article generation queued.',
            'article' => new ArticleResource($article->fresh(['keyword', 'wordpressSite', 'user'])),
        ], 202);
    }

    protected function attachKeywordMediaToArticle($keyword, $article): void
    {
        $this->mediaParser->createAssetsForKeyword($keyword);

        MediaAsset::query()
            ->where('tenant_id', $article->tenant_id)
            ->whereNull('article_id')
            ->where('campaign_id', $article->campaign_id)
            ->where('metadata->keyword', $keyword->keyword)
            ->update(['article_id' => $article->id]);
    }
}
