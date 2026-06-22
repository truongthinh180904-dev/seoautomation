<?php

namespace App\Services\Media;

use App\Models\Article;
use App\Models\MediaAsset;
use App\Jobs\Media\DownloadImageJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GeminiImageGenerationService
{
    public function __construct(
        protected StockImageSearchService $stockSearch,
        protected ImageOptimizationService $optimizer
    ) {}

    public function ensureInlineImages(Article $article): int
    {
        $target = $this->targetImageCount($article);
        if ($target <= 0) {
            return 0;
        }

        $existing = MediaAsset::query()
            ->where('article_id', $article->id)
            ->where('metadata->role', 'inline')
            ->whereIn('status', ['pending', 'downloaded', 'uploaded'])
            ->count();

        $missing = max(0, $target - $existing);
        if ($missing <= 0) {
            return 0;
        }

        $created = 0;
        $strategy = $this->sourceStrategy($article);

        if (in_array($strategy, ['stock', 'hybrid'], true)) {
            $created += $this->createStockAssets($article, $missing);
            $missing = max(0, $target - ($existing + $created));
        }

        if ($missing > 0 && in_array($strategy, ['ai', 'hybrid'], true) && config('ai.image_generation.enabled', true)) {
            foreach ($this->imagePrompts($article, $missing) as $prompt) {
                $created += $this->generateAndStore($article, $prompt);
            }
        }

        return $created;
    }

    protected function createStockAssets(Article $article, int $missing): int
    {
        $query = $this->stockQuery($article);
        $created = 0;

        foreach ($this->stockSearch->search($query, $missing) as $image) {
            $asset = MediaAsset::firstOrCreate(
                [
                    'tenant_id' => $article->tenant_id,
                    'article_id' => $article->id,
                    'source_url' => $image['url'],
                ],
                [
                    'campaign_id' => $article->campaign_id,
                    'source_type' => 'external_url',
                    'alt_text' => $image['alt'] ?: ($article->focus_keyword ?: $article->title),
                    'caption' => $image['caption'] ?? null,
                    'credit' => $image['credit'] ?? null,
                    'status' => 'pending',
                    'metadata' => [
                        'role' => 'inline',
                        'keyword' => $article->focus_keyword,
                        'provider' => $image['provider'] ?? 'stock',
                        'search_query' => $query,
                    ],
                ]
            );

            if ($asset->wasRecentlyCreated) {
                DownloadImageJob::dispatch($asset->id)->onQueue('imports');
                $created++;
            }
        }

        return $created;
    }

    protected function generateAndStore(Article $article, string $prompt): int
    {
        $apiKey = config('ai.providers.gemini.api_key');
        if (!$apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is missing for image generation.');
        }

        $model = config('ai.image_generation.model', 'imagen-4.0-generate-001');
        $response = Http::withOptions([
            'verify' => (bool) config('ai.http.verify_ssl', true),
        ])->timeout(180)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:predict", [
                'instances' => [['prompt' => $prompt]],
                'parameters' => array_filter([
                    'sampleCount' => 1,
                    'aspectRatio' => config('ai.image_generation.aspect_ratio', '16:9'),
                    'imageSize' => config('ai.image_generation.image_size', '1K'),
                    'personGeneration' => 'allow_adult',
                ]),
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Gemini image generation failed: ' . $response->body());
        }

        $base64 = $this->extractImageBytes($response->json());
        if (!$base64) {
            throw new RuntimeException('Gemini image generation response did not include image bytes.');
        }

        $bytes = base64_decode($base64, true);
        if ($bytes === false) {
            throw new RuntimeException('Gemini image generation returned invalid base64 image bytes.');
        }

        $image = $this->optimizer->optimize($bytes, 'image/png');
        $path = sprintf(
            'media/%d/ai-generated/%s.%s',
            $article->tenant_id,
            Str::uuid()->toString(),
            $image['extension']
        );
        Storage::put($path, $image['contents']);

        MediaAsset::create([
            'tenant_id' => $article->tenant_id,
            'campaign_id' => $article->campaign_id,
            'article_id' => $article->id,
            'source_type' => 'ai_generated',
            'local_path' => $path,
            'alt_text' => $article->focus_keyword ?: $article->title,
            'caption' => $this->captionFromPrompt($prompt),
            'description' => $prompt,
            'status' => 'downloaded',
            'metadata' => [
                'role' => 'inline',
                'keyword' => $article->focus_keyword,
                'provider' => 'gemini',
                'model' => config('ai.image_generation.model'),
                'prompt' => $prompt,
                'mime_type' => $image['mime_type'],
                'size_bytes' => $image['size_bytes'],
                'original_size_bytes' => $image['original_size_bytes'],
                'width' => $image['width'],
                'height' => $image['height'],
                'optimized' => $image['optimized'],
            ],
        ]);

        return 1;
    }

    protected function targetImageCount(Article $article): int
    {
        $plan = $article->media_plan ?? [];
        $keywordMeta = $article->keyword?->meta ?? [];
        $default = (int) config('ai.image_generation.default_inline_count', 3);
        $max = (int) config('ai.image_generation.max_inline_count', 10);
        $target = (int) ($plan['inline_image_count'] ?? $keywordMeta['inline_image_count'] ?? $default);

        return max(0, min($max, $target));
    }

    protected function sourceStrategy(Article $article): string
    {
        $plan = $article->media_plan ?? [];
        $keywordMeta = $article->keyword?->meta ?? [];
        $strategy = (string) ($plan['image_source'] ?? $keywordMeta['image_source'] ?? config('ai.image_generation.source_strategy', 'hybrid'));

        return in_array($strategy, ['stock', 'ai', 'hybrid'], true) ? $strategy : 'hybrid';
    }

    protected function stockQuery(Article $article): string
    {
        $plan = $article->media_plan ?? [];
        $keyword = $article->focus_keyword ?: $article->keyword?->keyword ?: $article->title;
        $prompt = trim((string) ($plan['image_search_query'] ?? $plan['image_generation_prompt'] ?? ''));

        return $prompt !== '' ? $prompt : $keyword;
    }

    protected function imagePrompts(Article $article, int $count): array
    {
        $plan = $article->media_plan ?? [];
        $keyword = $article->focus_keyword ?: $article->keyword?->keyword ?: $article->title;
        $basePrompt = trim((string) ($plan['image_generation_prompt'] ?? $article->keyword?->meta['image_generation_prompt'] ?? ''));
        $headings = $this->extractHeadings((string) $article->content);
        $prompts = [];

        for ($i = 0; $i < $count; $i++) {
            $section = $headings[$i] ?? $article->title;
            $prompts[] = trim(implode(' ', array_filter([
                $basePrompt ?: "A clean premium editorial website image for a Vietnamese SEO article about {$keyword}.",
                "Section concept: {$section}.",
                'Photorealistic, modern, elegant composition, natural light, no watermark, no text overlay, suitable for a WordPress article.',
            ])));
        }

        return $prompts;
    }

    protected function extractHeadings(string $html): array
    {
        preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $matches);

        return array_values(array_filter(array_map(
            fn (string $heading) => trim(strip_tags($heading)),
            $matches[1] ?? []
        )));
    }

    protected function extractImageBytes(array $payload): ?string
    {
        return data_get($payload, 'predictions.0.bytesBase64Encoded')
            ?: data_get($payload, 'predictions.0.image.bytesBase64Encoded')
            ?: data_get($payload, 'predictions.0.image.imageBytes')
            ?: data_get($payload, 'generatedImages.0.image.imageBytes');
    }

    protected function captionFromPrompt(string $prompt): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $prompt) ?? $prompt), 0, 180);
    }
}
