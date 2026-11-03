<?php

namespace App\Jobs\AI;

use App\Agents\InternalLinkingAgent;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\InternalLink;
use App\Services\SEO\InternalLinkingService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OptimizeInternalLinksJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $backoff = [60, 180];

    public $timeout = 180;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'optimize_internal_links_'.$this->articleId;
    }

    public function __construct(public int $articleId)
    {
        $this->onQueue('ai-writing');
    }

    public function handle(InternalLinkingAgent $agent, InternalLinkingService $linkingService): void
    {
        $article = Article::with(['keyword', 'wordpressSite'])->find($this->articleId);

        if (! $article) {
            return;
        }

        if (data_get($article->pipeline_status, 'current') === 'cancelled' || $article->status === ArticleStatus::FAILED) {
            Log::info("OptimizeInternalLinksJob aborted: Article #{$article->id} was cancelled or failed.");

            return;
        }

        try {
            BelongsToTenant::setExecutionContext($article->tenant_id);
            BelongsToWorkspace::setExecutionContext($article->workspace_id);

            if ($article->wordpressSite) {
                $relatedPostsRaw = $linkingService->fetchRelatedPosts($article->wordpressSite, $article->keyword->keyword ?? '');

                if (! empty($relatedPostsRaw)) {
                    $relatedPosts = array_map(function ($post) {
                        return [
                            'id' => $post['id'] ?? '',
                            'title' => is_array($post['title']) ? ($post['title']['rendered'] ?? '') : ($post['title'] ?? ''),
                            'url' => $post['link'] ?? '',
                        ];
                    }, $relatedPostsRaw);

                    $context = [
                        'article_content' => $article->content,
                        'related_posts' => $relatedPosts,
                        'tenant_id' => $article->tenant_id,
                        'workspace_id' => $article->workspace_id,
                        'article_id' => $article->id,
                        'keyword_id' => $article->keyword_id,
                    ];

                    $result = $agent->execute($context);

                    if ($result->success) {
                        $data = $result->data;
                        if (! empty($data['content']) && $data['content'] !== $article->content) {
                            $article->content = $data['content'];
                            $article->save();
                        }

                        if (! empty($data['links_added'])) {
                            foreach ($data['links_added'] as $link) {
                                InternalLink::firstOrCreate([
                                    'source_article_id' => $article->id,
                                    'target_url' => $link['url'] ?? '',
                                ], [
                                    'tenant_id' => $article->tenant_id,
                                    'wordpress_site_id' => $article->wordpress_site_id,
                                    'anchor_text' => $link['anchor_text'] ?? '',
                                    'is_placed' => true,
                                ]);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OptimizeInternalLinksJob failed: '.$e->getMessage());
        } finally {
            BelongsToTenant::setExecutionContext(null);
            BelongsToWorkspace::setExecutionContext(null);

            $freshArticle = Article::find($this->articleId);
            $isCancelled = ! $freshArticle
                || data_get($freshArticle->pipeline_status, 'current') === 'cancelled'
                || $freshArticle->status === ArticleStatus::FAILED;

            if (! $isCancelled && class_exists(GenerateSeoMetadataJob::class)) {
                GenerateSeoMetadataJob::dispatch($this->articleId)->onQueue('ai-writing');
            }
        }
    }
}
