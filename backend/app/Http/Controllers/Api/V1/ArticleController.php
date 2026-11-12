<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Events\ArticleApproved;
use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateArticleRequest;
use App\Http\Requests\ReviewArticleActionRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Jobs\AI\GenerateOutlineJob;
use App\Jobs\Media\GenerateArticleImagesJob;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Models\MediaAsset;
use App\Models\Workspace;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Services\Article\ArticleContentFormatter;
use App\Services\Article\ArticlePipelineService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Cost\CostTrackingService;
use App\Services\Media\MediaParserService;
use App\Services\Queue\TaskService;
use App\Services\SEO\SEOAutoFixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        protected SEOAutoFixService $autoFixService,
        protected TaskService $taskService
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
        if (! $article) {
            abort(404);
        }

        return new ArticleResource($article);
    }

    public function update(int $id, UpdateArticleRequest $request)
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (! $article) {
            abort(404);
        }

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

        if (($request->validated()['status'] ?? null) === ArticleStatus::APPROVED->value && ! $wasApproved) {
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
        if (! $this->repository->deleteForTenant($id, $request->user()->tenant_id)) {
            abort(404);
        }

        return response()->json(null, 204);
    }

    public function retry(int $id, Request $request): JsonResponse
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (! $article || $article->status !== ArticleStatus::FAILED) {
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
        if (! $article) {
            abort(404);
        }

        $currentWorkspace = $request->attributes->get('current_workspace');
        if ($currentWorkspace && $article->workspace_id && $article->workspace_id !== $currentWorkspace->id) {
            abort(404);
        }

        $workspace = $currentWorkspace
            ?: ($article->workspace_id ? Workspace::where('id', $article->workspace_id)->where('legacy_tenant_id', $request->user()->tenant_id)->first() : null)
            ?: ($request->user()->default_workspace_id ? Workspace::where('id', $request->user()->default_workspace_id)->where('legacy_tenant_id', $request->user()->tenant_id)->first() : null);
        $quota = $workspace ? $this->costTracking->checkWorkspaceQuota($workspace) : $this->costTracking->checkQuota($request->user()->tenant_id);
        if (! $quota->canProceed) {
            return response()->json([
                'message' => $quota->message ?? 'AI quota exceeded for SEO auto-fix.',
            ], 402);
        }

        return (new ArticleResource($this->autoFixService->autoFix($article)))->additional([
            'message' => 'SEO auto-fix applied.',
        ]);
    }

    public function approveAndPublish(int $id, Request $request): JsonResponse
    {
        $article = $this->repository->findByIdForTenant($id, $request->user()->tenant_id);
        if (! $article) {
            abort(404);
        }

        if (! $article->wordpress_site_id) {
            $candidateSiteId = $request->input('wordpress_site_id')
                ?: $article->website?->wordpress_site_id
                ?: $article->campaign?->wordpress_site_id;

            if ($candidateSiteId) {
                $site = \App\Models\WordPressSite::where('tenant_id', $request->user()->tenant_id)->find($candidateSiteId);
                if ($site && (! $article->workspace_id || ! $site->workspace_id || $site->workspace_id === $article->workspace_id)) {
                    $article->update(['wordpress_site_id' => $site->id]);
                    $article->refresh();
                }
            }
        }

        if (! $article->wordpress_site_id) {
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
        if (! $article) {
            abort(404);
        }

        $currentWorkspace = $request->attributes->get('current_workspace');
        if ($currentWorkspace && $article->workspace_id && $article->workspace_id !== $currentWorkspace->id) {
            abort(404);
        }

        $workspace = $currentWorkspace
            ?: ($article->workspace_id ? Workspace::where('id', $article->workspace_id)->where('legacy_tenant_id', $request->user()->tenant_id)->first() : null)
            ?: ($request->user()->default_workspace_id ? Workspace::where('id', $request->user()->default_workspace_id)->where('legacy_tenant_id', $request->user()->tenant_id)->first() : null);
        $workspaceId = $workspace?->id ?: ($article->workspace_id ?: $request->user()->default_workspace_id);

        $imageCreditsCost = (int) config('ai_costs.credits.image_generation', 5);
        $imageStrategy = $article->campaign?->settings['image_source'] ?? $article->media_plan['image_source'] ?? config('ai.image_generation.source_strategy', 'hybrid');
        $aiImageEnabled = config('ai.image_generation.enabled', true);

        if ($aiImageEnabled && in_array($imageStrategy, ['ai', 'hybrid'], true)) {
            $targetWorkspace = $workspaceId ?: $article->workspace_id;
            if ($targetWorkspace && app()->bound(CreditLedgerService::class)) {
                $creditService = app(CreditLedgerService::class);
                try {
                    $creditService->checkCredits($targetWorkspace, $imageCreditsCost);
                } catch (InsufficientCreditsException $e) {
                    return response()->json([
                        'message' => 'Số dư credit không đủ để sinh ảnh AI.',
                        'required' => $imageCreditsCost,
                    ], 402);
                }
            } else {
                $quota = $workspace ? $this->costTracking->checkWorkspaceQuota($workspace) : $this->costTracking->checkQuota($request->user()->tenant_id);
                if (! $quota->canProceed) {
                    return response()->json([
                        'message' => $quota->message ?? 'AI quota exceeded for image generation.',
                    ], 402);
                }
            }
        }

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
        if (! $article) {
            abort(404);
        }

        return (new ArticleResource($article))->response();
    }

    /**
     * Approve or reject an article via review token (no auth required).
     */
    public function reviewAction(string $token, ReviewArticleActionRequest $request): JsonResponse
    {
        $article = $this->repository->findPendingReviewByToken($token);
        if (! $article) {
            abort(404);
        }

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

        $currentWorkspace = $request->attributes->get('current_workspace');
        $workspace = $currentWorkspace
            ?: ($request->user()->default_workspace_id ? Workspace::where('id', $request->user()->default_workspace_id)->where('legacy_tenant_id', $tenantId)->first() : null);
        $workspaceId = $workspace?->id ?: $request->user()->default_workspace_id;

        if (! empty($validated['keyword_id'])) {
            $keyword = $this->keywords->findByIdForTenant((int) $validated['keyword_id'], $tenantId);
            if (! $keyword) {
                abort(404);
            }
            if ($currentWorkspace && $keyword->workspace_id && $keyword->workspace_id !== $currentWorkspace->id) {
                abort(404);
            }
            $workspaceId = $keyword->workspace_id ?: $workspaceId;
        } else {
            $primaryKeyword = trim((string) ($validated['primary_keyword'] ?? ''));
            $websiteId = ! empty($validated['website_id']) ? (int) $validated['website_id'] : null;

            if (! $websiteId && ! empty($validated['brand_knowledge_id'])) {
                $kb = \App\Models\BrandKnowledgeBase::where('workspace_id', $workspaceId)->find((int) $validated['brand_knowledge_id']);
                $websiteId = $kb?->website_id;
            }
            if (! $websiteId) {
                $firstWebsite = \App\Models\Website::where('workspace_id', $workspaceId)->first()
                    ?: \App\Models\Website::where('tenant_id', $tenantId)->first();
                $websiteId = $firstWebsite?->id;
            }

            $keyword = \App\Models\Keyword::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'workspace_id' => $workspaceId,
                    'website_id' => $websiteId,
                    'keyword' => $primaryKeyword,
                ],
                [
                    'campaign_id' => $validated['campaign_id'] ?? null,
                    'wordpress_site_id' => $validated['wordpress_site_id'] ?? null,
                    'intent' => $validated['search_intent'] ?? 'informational',
                    'language' => $validated['content_language'] ?? 'vi',
                    'status' => KeywordStatus::PENDING,
                ]
            );
        }

        // Xây dựng Content Brief từ biểu mẫu tạo bài viết (Section 4)
        $contentBrief = array_filter([
            'brand_knowledge_id' => $validated['brand_knowledge_id'] ?? ($keyword->meta['content_brief']['brand_knowledge_id'] ?? null),
            'primary_keyword' => $validated['primary_keyword'] ?? $keyword->keyword,
            'topic_description' => $validated['topic_description'] ?? ($keyword->meta['content_brief']['topic_description'] ?? null),
            'content_language' => $validated['content_language'] ?? $keyword->language ?? 'vi',
            'secondary_keywords' => $validated['secondary_keywords'] ?? ($keyword->meta['content_brief']['secondary_keywords'] ?? null),
            'related_keywords' => $validated['related_keywords'] ?? ($keyword->meta['content_brief']['related_keywords'] ?? null),
            'local_seo_location' => $validated['local_seo_location'] ?? ($keyword->meta['content_brief']['local_seo_location'] ?? null),
            'target_product_service' => $validated['target_product_service'] ?? ($keyword->meta['content_brief']['target_product_service'] ?? null),
            'target_audience' => $validated['target_audience'] ?? ($keyword->meta['content_brief']['target_audience'] ?? null),
            'article_goal' => $validated['article_goal'] ?? ($keyword->meta['content_brief']['article_goal'] ?? null),
            'desired_length' => $validated['desired_length'] ?? ($keyword->meta['content_brief']['desired_length'] ?? null),
            'writing_style' => $validated['writing_style'] ?? ($keyword->meta['content_brief']['writing_style'] ?? null),
            'desired_cta' => $validated['desired_cta'] ?? ($keyword->meta['content_brief']['desired_cta'] ?? null),
            'reference_urls' => $validated['reference_urls'] ?? ($keyword->meta['content_brief']['reference_urls'] ?? null),
            'internal_link_urls' => $validated['internal_link_urls'] ?? ($keyword->meta['content_brief']['internal_link_urls'] ?? null),
            'competitor_urls' => $validated['competitor_urls'] ?? ($keyword->meta['content_brief']['competitor_urls'] ?? null),
            'desired_image_count' => $validated['desired_image_count'] ?? ($keyword->meta['content_brief']['desired_image_count'] ?? null),
            'special_requirements' => $validated['special_requirements'] ?? ($keyword->meta['content_brief']['special_requirements'] ?? null),
            'content_type' => $validated['content_type'] ?? ($keyword->meta['content_brief']['content_type'] ?? 'auto'),
            'search_intent' => $validated['search_intent'] ?? ($keyword->meta['content_brief']['search_intent'] ?? $keyword->intent ?? null),
            'require_outline_approval' => $validated['require_outline_approval'] ?? false,
        ], fn ($v) => $v !== null && $v !== '');

        $quota = $workspace ? $this->costTracking->checkWorkspaceQuota($workspace) : $this->costTracking->checkQuota($tenantId);

        if (! $quota->canProceed) {
            return response()->json([
                'message' => $quota->message ?? 'AI quota exceeded.',
                'quota' => $quota->toArray(),
            ], 402);
        }

        $article = DB::transaction(function () use ($tenantId, $workspaceId, $validated, $keyword, $request, $contentBrief) {
            $existingArticle = $this->repository->findByKeywordIdForTenant($keyword->id, $tenantId);
            $keywordMeta = $keyword->meta ?? [];
            $inlineImgCount = isset($contentBrief['desired_image_count'])
                ? (int) $contentBrief['desired_image_count']
                : ($keywordMeta['inline_image_count'] ?? config('ai.image_generation.default_inline_count', 3));

            if (! $existingArticle) {
                $article = $this->repository->create([
                    'tenant_id' => $tenantId,
                    'workspace_id' => $workspaceId ?: $keyword->workspace_id,
                    'website_id' => $keyword->website_id ?? ($validated['website_id'] ?? null),
                    'keyword_id' => $keyword->id,
                    'campaign_id' => $validated['campaign_id'] ?? $keyword->campaign_id,
                    'wordpress_site_id' => $validated['wordpress_site_id'] ?? $keyword->wordpress_site_id,
                    'user_id' => $request->user()->id,
                    'title' => 'Bài viết: '.$keyword->keyword,
                    'slug' => Str::slug($keyword->keyword),
                    'focus_keyword' => $keyword->keyword,
                    'primary_cta' => $contentBrief['desired_cta'] ?? null,
                    'featured_image_url' => $keywordMeta['featured_image_url'] ?? null,
                    'media_plan' => array_filter([
                        'featured_image_url' => $keywordMeta['featured_image_url'] ?? null,
                        'image_urls' => $keywordMeta['image_urls'] ?? null,
                        'internal_links' => $keywordMeta['internal_links'] ?? null,
                        'image_generation_prompt' => $keywordMeta['image_generation_prompt'] ?? null,
                        'image_search_query' => $keywordMeta['image_search_query'] ?? null,
                        'image_source' => $keywordMeta['image_source'] ?? config('ai.image_generation.source_strategy', 'hybrid'),
                        'inline_image_count' => $inlineImgCount,
                    ], fn ($v) => $v !== null),
                    'wp_tag_names' => ! empty($keywordMeta['wp_tag_names'])
                        ? array_values(array_filter(array_map('trim', explode(',', str_replace('|', ',', (string) $keywordMeta['wp_tag_names'])))))
                        : null,
                    'wp_slug' => $keywordMeta['wp_slug'] ?? null,
                    'excerpt' => $keywordMeta['wp_excerpt'] ?? null,
                    'status' => ArticleStatus::DRAFT,
                ]);
            } else {
                $existingArticle->update([
                    'status' => ArticleStatus::DRAFT,
                    'website_id' => $existingArticle->website_id ?: ($keyword->website_id ?? ($validated['website_id'] ?? null)),
                    'primary_cta' => $contentBrief['desired_cta'] ?? $existingArticle->primary_cta,
                    'review_notes' => null,
                    'rejection_reason' => null,
                    'media_plan' => array_merge($existingArticle->media_plan ?? [], array_filter([
                        'featured_image_url' => $keywordMeta['featured_image_url'] ?? null,
                        'image_urls' => $keywordMeta['image_urls'] ?? null,
                        'internal_links' => $keywordMeta['internal_links'] ?? null,
                        'image_generation_prompt' => $keywordMeta['image_generation_prompt'] ?? null,
                        'image_search_query' => $keywordMeta['image_search_query'] ?? null,
                        'image_source' => $keywordMeta['image_source'] ?? null,
                        'inline_image_count' => $inlineImgCount,
                    ], fn ($v) => $v !== null)),
                ]);
                $article = $existingArticle->fresh();
            }

            $this->pipeline->reset($article);
            $this->pipeline->start($article, 'queued', 'Đang chờ queue worker nhận job.');

            // Lưu Content Brief vào pipeline_status để các Job kế tiếp sử dụng
            $pipelineStatus = $article->fresh()->pipeline_status ?? [];
            $pipelineStatus['content_brief'] = $contentBrief;
            $article->update(['pipeline_status' => $pipelineStatus]);

            $this->attachKeywordMediaToArticle($keyword, $article);

            if ($workspaceId) {
                $this->taskService->createTask([
                    'workspace_id' => $workspaceId,
                    'campaign_id' => $article->campaign_id,
                    'keyword_id' => $keyword->id,
                    'article_id' => $article->id,
                    'triggered_by' => $request->user()->id,
                    'type' => 'full_article_pipeline',
                    'current_step' => 'queued',
                    'credits_reserved' => (int) config('ai_costs.credits.standard_article', 20),
                ]);
            }

            $keyword->update(['status' => KeywordStatus::PROCESSING]);

            return $article->fresh();
        });

        GenerateOutlineJob::dispatch($keyword->id, $article->id)->onQueue('ai-writing');

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
