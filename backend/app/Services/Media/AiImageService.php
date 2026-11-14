<?php

namespace App\Services\Media;

use App\Models\Article;
use App\Models\MediaAsset;
use App\Services\Billing\CreditLedgerService;
use App\Services\Cost\CostTrackingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AiImageService
{
    public function __construct(
        protected ImageOptimizationService $optimizer,
        protected ?CostTrackingService $costTracking = null,
        protected ?CreditLedgerService $creditLedger = null,
    ) {
        $this->costTracking = $this->costTracking ?? app(CostTrackingService::class);
        $this->creditLedger = $this->creditLedger ?? app(CreditLedgerService::class);
    }

    /**
     * Build an optimized SEO image prompt based on an H2 heading and article context.
     */
    public function buildPromptFromHeading(string $heading, Article $article): string
    {
        $cleanHeading = trim(strip_tags($heading));
        $keyword = trim((string) ($article->focus_keyword ?: $article->title));

        return sprintf(
            'A photorealistic, modern, high-definition editorial illustration for the topic "%s", relating to "%s". 16:9 aspect ratio, clean natural lighting, commercial presentation, no text overlay, no watermarks.',
            $cleanHeading,
            $keyword
        );
    }

    /**
     * Build an SEO-friendly ALT text tag containing the focus keyword and heading concept.
     */
    public function buildAltText(string $heading, Article $article): string
    {
        $cleanHeading = trim(strip_tags($heading));
        $keyword = trim((string) ($article->focus_keyword ?: $article->title));

        if ($keyword !== '' && mb_stripos($cleanHeading, $keyword) === false) {
            $alt = sprintf('%s - %s', $keyword, $cleanHeading);
        } else {
            $alt = $cleanHeading !== '' ? $cleanHeading : $keyword;
        }

        return Str::limit($alt, 125, '');
    }

    /**
     * Generate an AI image for a specific H2 section using Google Imagen, optimize to WebP and save MediaAsset.
     */
    public function generateImageForHeading(Article $article, string $heading, ?string $customPrompt = null): MediaAsset
    {
        $prompt = $customPrompt ?: $this->buildPromptFromHeading($heading, $article);
        $altText = $this->buildAltText($heading, $article);

        $imageCredits = (int) config('ai_costs.credits.image_generation', 5);

        // 1. Credit check & reservation
        if ($this->creditLedger && $article->workspace_id) {
            $this->creditLedger->checkCredits($article->workspace_id, $imageCredits);
        }

        $apiKey = config('ai.providers.gemini.api_key');
        if (! $apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is missing for image generation.');
        }

        $model = config('ai.image_generation.model', 'imagen-3.0-generate-002');

        // 2. Call Google Imagen API
        $response = Http::withOptions([
            'verify' => (bool) config('ai.http.verify_ssl', true),
        ])->timeout(120)
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

        if (! $response->successful()) {
            throw new RuntimeException('Imagen image generation failed: '.$response->body());
        }

        $base64 = $this->extractImageBytes($response->json());
        if (! $base64) {
            throw new RuntimeException('Imagen API response did not contain image bytes.');
        }

        $rawBytes = base64_decode($base64, true);
        if ($rawBytes === false) {
            throw new RuntimeException('Invalid base64 payload received from Imagen API.');
        }

        // 3. Optimize image & compress to WebP (< 100KB)
        $optimized = $this->optimizer->optimize($rawBytes, 'image/png');

        $filename = sprintf(
            'media/%d/ai-generated/%s.%s',
            $article->tenant_id,
            Str::uuid()->toString(),
            $optimized['extension'] ?? 'webp'
        );

        Storage::put($filename, $optimized['contents']);

        // 4. Create MediaAsset entity
        $mediaAsset = MediaAsset::create([
            'tenant_id' => $article->tenant_id,
            'workspace_id' => $article->workspace_id,
            'campaign_id' => $article->campaign_id,
            'article_id' => $article->id,
            'source_type' => 'ai_generated',
            'local_path' => $filename,
            'alt_text' => $altText,
            'caption' => Str::limit(trim(strip_tags($heading)), 180),
            'description' => $prompt,
            'status' => 'downloaded',
            'metadata' => [
                'role' => 'inline',
                'heading' => $heading,
                'keyword' => $article->focus_keyword,
                'provider' => 'imagen',
                'model' => $model,
                'prompt' => $prompt,
                'mime_type' => $optimized['mime_type'],
                'size_bytes' => $optimized['size_bytes'],
                'original_size_bytes' => $optimized['original_size_bytes'] ?? strlen($rawBytes),
                'width' => $optimized['width'] ?? 1200,
                'height' => $optimized['height'] ?? 675,
                'optimized' => $optimized['optimized'],
            ],
        ]);

        // 5. Cost tracking & Credit deduction
        if ($this->costTracking) {
            $this->costTracking->logUsage([
                'tenant_id' => $article->tenant_id,
                'workspace_id' => $article->workspace_id,
                'campaign_id' => $article->campaign_id,
                'article_id' => $article->id,
                'job_type' => 'image_generation',
                'provider' => 'gemini',
                'model' => $model,
                'cost_usd' => 0.03,
                'credits_charged' => $imageCredits,
                'status' => 'success',
            ]);
        }

        if ($this->creditLedger && $article->workspace_id) {
            $this->creditLedger->deductCredits(
                workspace: $article->workspace_id,
                amount: $imageCredits,
                referenceType: 'article_image',
                referenceId: $article->id,
                idempotencyKey: sprintf('deduct:%d:ai_image:%d:%s', $article->workspace_id, $article->id, Str::slug(Str::limit($heading, 25))),
                metadata: ['job' => 'image_generation', 'model' => $model, 'heading' => $heading]
            );
        }

        return $mediaAsset;
    }

    /**
     * Generate images for all H2 headings in the article up to max limit.
     *
     * @return array<int, MediaAsset>
     */
    public function generateImagesForArticle(Article $article, int $maxImages = 3): array
    {
        $headings = $this->extractH2Headings((string) $article->content);
        if (empty($headings) && is_array($article->outline)) {
            foreach ($article->outline as $section) {
                if (! empty($section['heading'])) {
                    $headings[] = (string) $section['heading'];
                }
            }
        }

        $assets = [];
        $headingsToProcess = array_slice($headings, 0, $maxImages);

        foreach ($headingsToProcess as $heading) {
            $assets[] = $this->generateImageForHeading($article, $heading);
        }

        return $assets;
    }

    /**
     * Extract H2 headings from HTML content.
     *
     * @return array<int, string>
     */
    public function extractH2Headings(string $html): array
    {
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches);

        return array_values(array_filter(array_map(
            fn (string $heading) => trim(strip_tags($heading)),
            $matches[1] ?? []
        )));
    }

    /**
     * Extract image bytes from Imagen response.
     */
    protected function extractImageBytes(array $payload): ?string
    {
        return data_get($payload, 'predictions.0.bytesBase64Encoded')
            ?: data_get($payload, 'predictions.0.image.bytesBase64Encoded')
            ?: data_get($payload, 'predictions.0.image.imageBytes')
            ?: data_get($payload, 'generatedImages.0.image.imageBytes');
    }
}
