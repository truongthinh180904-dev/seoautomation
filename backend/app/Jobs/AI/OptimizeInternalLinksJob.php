<?php

namespace App\Jobs\AI;

use App\Agents\InternalLinkingAgent;
use App\Models\Article;
use App\Models\InternalLink;
use App\Services\SEO\InternalLinkingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OptimizeInternalLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [60, 180];

    public function __construct(public int $articleId)
    {
        $this->onQueue('ai-writing');
    }

    public function handle(InternalLinkingAgent $agent, InternalLinkingService $linkingService): void
    {
        try {
            $article = Article::with(['keyword', 'wordpressSite'])->find($this->articleId);

            if (!$article || !$article->wordpressSite) {
                return;
            }

            $relatedPostsRaw = $linkingService->fetchRelatedPosts($article->wordpressSite, $article->keyword->keyword ?? '');
            
            if (!empty($relatedPostsRaw)) {
                $relatedPosts = array_map(function ($post) {
                    return [
                        'id' => $post['id'] ?? '',
                        'title' => is_array($post['title']) ? ($post['title']['rendered'] ?? '') : ($post['title'] ?? ''),
                        'url' => $post['link'] ?? ''
                    ];
                }, $relatedPostsRaw);

                $context = [
                    'article_content' => $article->content,
                    'related_posts' => $relatedPosts,
                    'tenant_id' => $article->tenant_id,
                    'article_id' => $article->id,
                    'keyword_id' => $article->keyword_id,
                ];

                $result = $agent->execute($context);

                if ($result->success) {
                    $data = $result->data;
                    if (!empty($data['content']) && $data['content'] !== $article->content) {
                        $article->content = $data['content'];
                        $article->save();
                    }

                    if (!empty($data['links_added'])) {
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
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("OptimizeInternalLinksJob failed: " . $e->getMessage());
        } finally {
            if (class_exists(GenerateSeoMetadataJob::class)) {
                GenerateSeoMetadataJob::dispatch($this->articleId)->onQueue('ai-writing');
            }
        }
    }
}
