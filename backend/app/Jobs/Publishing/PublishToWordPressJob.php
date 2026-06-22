<?php

namespace App\Jobs\Publishing;

use App\DTOs\PublishingDTO;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\MediaAsset;
use App\Models\PublishingLog;
use App\Jobs\Media\DownloadImageJob;
use App\Jobs\Media\UploadToWordPressMediaJob;
use App\Services\Article\ArticlePipelineService;
use App\Services\WordPress\WordPressPreflightCheckService;
use App\Services\WordPress\WordPressPublishService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishToWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300, 600];
    public $timeout = 120;

    public function __construct(
        public int $articleId,
        public int $wordpressSiteId
    ) {
        $this->onQueue('publishing');
    }

    public function handle(
        WordPressPublishService $publishService,
        WordPressPreflightCheckService $preflight,
        ArticlePipelineService $pipeline
    ): void
    {
        $startTime = microtime(true);
        $article = Article::with('wordpressSite')->find($this->articleId);

        if (!$article) {
            return;
        }

        $pipeline->start($article, 'publishing', 'Đang chuẩn bị đăng WordPress.');

        if (in_array($article->status, [ArticleStatus::PUBLISHING, ArticleStatus::PUBLISHED], true)) {
            return;
        }

        if ($article->status !== ArticleStatus::APPROVED) {
            $pipeline->fail($article, 'publishing', 'Article is not approved for publishing.');
            Log::warning("PublishToWordPressJob aborted: Article {$article->id} is not APPROVED (status: {$article->status->value})");
            return;
        }

        $site = $article->wordpressSite ?? \App\Models\WordPressSite::find($this->wordpressSiteId);
        
        if (!$site) {
            $pipeline->fail($article, 'publishing', 'No WordPress site configured.');
            Log::warning("PublishToWordPressJob aborted: No WordPress site configured for article {$article->id}");
            return;
        }

        $article->update(['status' => ArticleStatus::PUBLISHING]);

        $mediaAssets = MediaAsset::query()
            ->where('article_id', $article->id)
            ->whereIn('metadata->role', ['featured', 'inline'])
            ->get();
        $featuredAsset = $mediaAssets->first(fn (MediaAsset $asset) => ($asset->metadata['role'] ?? null) === 'featured');

        $pendingAssets = $mediaAssets->filter(fn (MediaAsset $asset) => $asset->status->value === 'pending');
        if ($pendingAssets->isNotEmpty()) {
            $pipeline->start($article, 'media_download', 'Đang tải ảnh từ Excel trước khi đăng.');
            $pendingAssets->each(fn (MediaAsset $asset) => DownloadImageJob::dispatch($asset->id)->onQueue('imports'));

            $article->update([
                'status' => ArticleStatus::APPROVED,
                'review_notes' => 'Image download queued before publishing.',
            ]);
            return;
        }

        $downloadedAssets = $mediaAssets->filter(fn (MediaAsset $asset) => $asset->status->value === 'downloaded' && !$asset->wordpress_media_id);
        if ($downloadedAssets->isNotEmpty()) {
            $pipeline->start($article, 'media_upload', 'Đang đưa ảnh lên WordPress trước khi đăng.');
            $downloadedAssets->each(fn (MediaAsset $asset) => UploadToWordPressMediaJob::dispatch($asset->id, $site->id)->onQueue('publishing'));

            $article->update([
                'status' => ArticleStatus::APPROVED,
                'review_notes' => 'Image upload queued before publishing.',
            ]);
            return;
        }

        if ($featuredAsset?->wordpress_media_id) {
            $article->image_assets = array_merge($article->image_assets ?? [], [
                'featured' => [
                    'media_asset_id' => $featuredAsset->id,
                    'wordpress_media_id' => $featuredAsset->wordpress_media_id,
                    'wordpress_media_url' => $featuredAsset->wordpress_media_url,
                ],
            ]);
            $article->save();
        }

        $preflightResult = $preflight->runAll($article, $site);
        if (!$preflightResult['passed']) {
            $pipeline->fail($article, 'publishing', 'WordPress preflight failed.');
            $article->update([
                'status' => ArticleStatus::FAILED,
                'quality_report' => array_merge($article->quality_report ?? [], ['wordpress_preflight' => $preflightResult]),
                'review_notes' => 'WordPress preflight failed.',
            ]);
            return;
        }

        $dto = new PublishingDTO(
            title: $article->title,
            content: $this->contentWithInlineImages($article),
            publishStatus: $article->wp_status ?: ($article->scheduled_publish_at ? 'future' : 'publish'),
            excerpt: $article->excerpt,
            slug: $article->wp_slug ?: $article->slug,
            authorId: $article->wp_author_id ?: $site->default_author_id,
            categoryIds: $article->wp_category_ids ?: array_filter([$site->default_category_id]),
            tagIds: $article->wp_tag_ids ?: [],
            tagNames: $article->wp_tag_names ?: [],
            featuredMediaId: data_get($article->image_assets, 'featured.wordpress_media_id'),
            seoTitle: $article->seo_title,
            seoDescription: $article->seo_description,
            scheduledAt: $article->scheduled_publish_at ? $article->scheduled_publish_at->toIso8601String() : null,
            postType: $article->wp_post_type ?: 'post',
            canonicalUrl: $article->canonical_url
        );

        try {
            $result = $publishService->publish($dto, $site);

            $article->update([
                'status' => ArticleStatus::PUBLISHED,
                'wordpress_post_id' => $result['post_id'],
                'wordpress_post_url' => $result['url'],
                'published_at' => now(),
            ]);
            $article->keyword?->update([
                'status' => \App\Enums\KeywordStatus::COMPLETED,
                'processed_at' => now(),
            ]);
            $pipeline->complete($article->fresh(), 'publishing', 'Đăng WordPress thành công.');
            $pipeline->complete($article->fresh(), 'done', 'Bài viết đã được đăng.');

            PublishingLog::create([
                'tenant_id' => $article->tenant_id,
                'article_id' => $article->id,
                'wordpress_site_id' => $site->id,
                'wordpress_post_id' => $result['post_id'],
                'attempt_number' => $this->attempts(),
                'status' => 'success',
                'http_status_code' => 200, // or 201
                'published_url' => $result['url'],
                'wordpress_edit_url' => $result['edit_url'] ?? null,
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ]);


        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function failed(Throwable $exception): void
    {
        $article = Article::find($this->articleId);
        if ($article) {
            app(ArticlePipelineService::class)->fail($article, 'publishing', $exception->getMessage());

            $article->update(['status' => ArticleStatus::FAILED, 'review_notes' => 'Publishing failed: ' . $exception->getMessage()]);
            
            PublishingLog::create([
                'tenant_id' => $article->tenant_id,
                'article_id' => $article->id,
                'wordpress_site_id' => $this->wordpressSiteId,
                'attempt_number' => $this->attempts(),
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'duration_ms' => 0,
            ]);

        }
    }

    private function contentWithInlineImages(Article $article): string
    {
        $content = (string) $article->content;
        $inlineAssets = MediaAsset::query()
            ->where('article_id', $article->id)
            ->where('metadata->role', 'inline')
            ->whereNotNull('wordpress_media_url')
            ->get();

        foreach ($inlineAssets as $index => $asset) {
            if (str_contains($content, (string) $asset->wordpress_media_url)) {
                continue;
            }

            $alt = e($asset->alt_text ?: $article->focus_keyword ?: $article->title);
            $caption = e($asset->caption ?: $asset->alt_text ?: '');
            $figure = '<figure class="wp-block-image"><img src="' . e($asset->wordpress_media_url) . '" alt="' . $alt . '" />';

            if ($caption !== '') {
                $figure .= '<figcaption>' . $caption . '</figcaption>';
            }

            $figure .= '</figure>';
            $content = $this->insertFigureAfterHeading($content, $figure, $index + 1);
        }

        return $content;
    }

    private function insertFigureAfterHeading(string $content, string $figure, int $position): string
    {
        $matches = [];
        preg_match_all('/<\/h2>/i', $content, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return $content . "\n\n" . $figure;
        }

        $targetIndex = min($position - 1, count($matches[0]) - 1);
        $offset = $matches[0][$targetIndex][1] + strlen($matches[0][$targetIndex][0]);

        return substr($content, 0, $offset) . "\n\n" . $figure . substr($content, $offset);
    }
}
