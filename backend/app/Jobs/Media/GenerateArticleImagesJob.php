<?php

namespace App\Jobs\Media;

use App\Models\Article;
use App\Enums\ArticleStatus;
use App\Jobs\Publishing\PublishToWordPressJob;
use App\Services\Article\ArticlePipelineService;
use App\Services\Media\GeminiImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateArticleImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public array $backoff = [120, 300];
    public int $timeout = 600;

    public function __construct(public int $articleId)
    {
        $this->onQueue('imports');
    }

    public function handle(GeminiImageGenerationService $generator, ArticlePipelineService $pipeline): void
    {
        $article = Article::with('keyword')->find($this->articleId);
        if (!$article) {
            return;
        }

        try {
            $pipeline->start($article, 'media_download', 'AI đang tạo ảnh minh họa còn thiếu.');
            $created = $generator->ensureInlineImages($article);

            $article = $article->fresh();
            if ($created > 0) {
                $pipeline->complete($article, 'media_download', "Đã tạo {$created} ảnh AI cho bài viết.");

                if ($article->wordpress_site_id && $article->status === ArticleStatus::APPROVED) {
                    PublishToWordPressJob::dispatch($article->id, $article->wordpress_site_id)->onQueue('publishing');
                }
            } else {
                $pipeline->complete($article, 'media_download', 'Bài viết đã đủ số ảnh theo cấu hình.');
            }
        } catch (Throwable $exception) {
            Log::warning('GenerateArticleImagesJob failed without blocking article generation', [
                'article_id' => $article->id,
                'error' => $exception->getMessage(),
            ]);

            $pipeline->fail($article, 'media_download', 'AI image generation failed: ' . $exception->getMessage());
        }
    }
}
