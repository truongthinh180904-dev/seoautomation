<?php

namespace App\Services\Media;

use App\Models\Article;
use App\Models\Website;
use App\Services\Brand\BrandKnowledgeService;
use App\Services\Brand\IndustryProfileRegistry;
use Illuminate\Support\Str;

class ImagePlannerService
{
    protected const INDUSTRY_VISUAL_DIRECTIONS = [
        IndustryProfileRegistry::INDUSTRY_HEALTHCARE => [
            'environment' => 'clean, modern, well-lit medical or dental clinic environment',
            'style' => 'trustworthy healthcare editorial photography, soft natural clinical lighting, calm and reassuring mood',
            'subjects' => [
                'professional medical consultation with anatomical model or digital tablet',
                'modern sterile clinical diagnostic equipment in a bright room',
                'preventive health checkup and patient care guidance',
            ],
        ],
        IndustryProfileRegistry::INDUSTRY_REAL_ESTATE => [
            'environment' => 'contemporary architectural space, landscaped urban residential area, bright modern interior',
            'style' => 'architectural & real estate photography, wide angle, golden hour or bright daylight, balanced perspective',
            'subjects' => [
                'modern residential building exterior with green landscape and infrastructure',
                'spacious sunlit living room interior with refined contemporary design',
                'urban masterplan overview and architectural blueprints on a wooden table',
            ],
        ],
        IndustryProfileRegistry::INDUSTRY_LEGAL => [
            'environment' => 'refined law firm conference room or professional advisory office',
            'style' => 'corporate editorial photography, warm neutral tones, authoritative and trustworthy atmosphere',
            'subjects' => [
                'legal advisor reviewing official dossier and regulatory documents at a desk',
                'professional consultation meeting in a modern office with bookshelves',
                'structured checklist and compliance workflow review',
            ],
        ],
        IndustryProfileRegistry::INDUSTRY_TECHNOLOGY => [
            'environment' => 'modern tech workspace, collaborative software engineering studio',
            'style' => 'clean B2B SaaS editorial photography, crisp lighting, modern minimalist aesthetic',
            'subjects' => [
                'team analyzing data charts and workflow metrics on a large monitor (abstract UI without readable text)',
                'software architecture planning session in a modern glass-walled office',
                'cloud infrastructure and cybersecurity concept in a sleek enterprise setting',
            ],
        ],
        IndustryProfileRegistry::INDUSTRY_ECOMMERCE => [
            'environment' => 'studio product showcase or authentic lifestyle usage setting',
            'style' => 'high-resolution commercial product & lifestyle photography, natural shadows, sharp focus',
            'subjects' => [
                'neatly arranged product flatlay with natural props in a bright studio',
                'close-up of material quality and craftsmanship details',
                'careful packaging and quality inspection workflow',
            ],
        ],
        IndustryProfileRegistry::INDUSTRY_TRAVEL => [
            'environment' => 'scenic destination landscape, boutique resort, cultural landmark',
            'style' => 'National Geographic & Condé Nast Traveler inspired travel photography, vibrant natural light',
            'subjects' => [
                'panoramic scenic view of destination landscape under clear sky',
                'inviting resort hospitality space with local architectural charm',
                'travel preparation essentials with map and camera on a table',
            ],
        ],
        IndustryProfileRegistry::INDUSTRY_FINANCE => [
            'environment' => 'modern financial advisory office, executive workspace',
            'style' => 'professional corporate editorial photography, composed and analytical mood',
            'subjects' => [
                'financial planning session with charts and calculator on a clean desk',
                'portfolio risk management and strategic consultation between advisors',
                'digital banking security and wealth management concept',
            ],
        ],
    ];

    public function __construct(
        protected BrandKnowledgeService $brandKnowledgeService,
        protected IndustryProfileRegistry $industryRegistry
    ) {}

    /**
     * Lập kế hoạch hình ảnh (Featured + Inline Images) gắn với từng H2/H3, sinh Prompt theo ngành và chuẩn hóa Image SEO (Section 8).
     *
     * @return array<string, mixed>
     */
    public function buildAndAttachPlan(Article $article, ?int $inlineCountOverride = null): array
    {
        $website = $article->website ?: ($article->website_id ? Website::find($article->website_id) : null);
        $brandKnowledgeId = (int) (
            data_get($article->pipeline_status, 'content_brief.brand_knowledge_id')
            ?: data_get($article->outline, 'content_brief.brand_knowledge_id')
            ?: 0
        ) ?: null;

        $brandContext = $website
            ? $this->brandKnowledgeService->extractStructuredContext($website, $brandKnowledgeId)
            : $this->brandKnowledgeService->extractStructuredContext(0, null);

        $industryKey = (string) (
            data_get($article->outline, 'strategy_analysis.step_1_business_context.industry_key')
            ?: ($brandContext['industry_key'] ?? IndustryProfileRegistry::INDUSTRY_OTHER)
        );
        $industryProfile = $this->industryRegistry->getProfile($industryKey);

        $keyword = trim((string) ($article->focus_keyword ?: $article->keyword?->keyword ?: $article->title));
        $keywordSlug = Str::slug($keyword) ?: 'bai-viet-seo';
        $headings = $this->extractHeadings((string) $article->content);
        $h2Headings = $this->extractH2Headings((string) $article->content);

        $existingPlan = is_array($article->media_plan) ? $article->media_plan : [];
        $wordCount = (int) ($article->word_count ?: str_word_count(strip_tags((string) $article->content)));
        $lengthTier = (string) (
            data_get($article->pipeline_status, 'content_brief.desired_length')
            ?: data_get($article->outline, 'content_brief.desired_length')
            ?: ''
        );

        $explicitInlineCount = $inlineCountOverride
            ?? (isset($existingPlan['inline_image_count']) ? (int) $existingPlan['inline_image_count'] : null);

        $inlineCount = $explicitInlineCount !== null && $explicitInlineCount > 0
            ? $explicitInlineCount
            : $this->calculateOptimalInlineImageCount($wordCount, count($h2Headings), $lengthTier);
        $inlineCount = max(1, min(10, $inlineCount));

        $visualConfig = self::INDUSTRY_VISUAL_DIRECTIONS[$industryKey] ?? [
            'environment' => 'modern professional business environment',
            'style' => 'clean commercial editorial photography, natural lighting, high detail',
            'subjects' => [
                'professional consultation and strategic overview',
                'step-by-step workflow and quality evaluation process',
                'practical application and customer support excellence',
            ],
        ];

        $featuredPlan = $this->buildSingleImageItem(
            role: 'featured',
            index: 0,
            heading: (string) ($article->title ?: $keyword),
            keyword: $keyword,
            keywordSlug: $keywordSlug,
            industryKey: $industryKey,
            industryName: (string) ($industryProfile['name'] ?? 'Đa ngành'),
            visualConfig: $visualConfig
        );

        $distributedHeadings = $this->selectDistributedHeadings(
            ! empty($h2Headings) ? $h2Headings : $headings,
            $inlineCount,
            $keyword
        );

        $inlineItems = [];
        for ($i = 0; $i < $inlineCount; $i++) {
            $targetInfo = $distributedHeadings[$i];
            $inlineItems[] = array_merge(
                $this->buildSingleImageItem(
                    role: 'inline',
                    index: $i + 1,
                    heading: $targetInfo['heading'],
                    keyword: $keyword,
                    keywordSlug: $keywordSlug,
                    industryKey: $industryKey,
                    industryName: (string) ($industryProfile['name'] ?? 'Đa ngành'),
                    visualConfig: $visualConfig
                ),
                [
                    'target_h2_index' => $targetInfo['h2_index'],
                    'placement' => 'after_first_paragraph_of_h2',
                ]
            );
        }

        $imagePlan = [
            'industry_key' => $industryKey,
            'industry_name' => $industryProfile['name'] ?? 'Đa ngành',
            'word_count_basis' => $wordCount,
            'h2_count' => count($h2Headings),
            'optimal_inline_count' => $inlineCount,
            'featured_image_plan' => $featuredPlan,
            'inline_image_plans' => $inlineItems,
            'planned_at' => now()->toIso8601String(),
        ];

        $mergedMediaPlan = array_merge($existingPlan, [
            'image_plan' => $imagePlan,
        ]);

        $article->update(['media_plan' => $mergedMediaPlan]);

        return $imagePlan;
    }

    /**
     * @param  array<string, mixed>  $visualConfig
     * @return array<string, mixed>
     */
    protected function buildSingleImageItem(
        string $role,
        int $index,
        string $heading,
        string $keyword,
        string $keywordSlug,
        string $industryKey,
        string $industryName,
        array $visualConfig
    ): array {
        $subjects = $visualConfig['subjects'] ?? ['professional subject related to the topic'];
        $subjectHint = $subjects[$index % count($subjects)];
        $cleanHeading = trim(preg_replace('/^\d+[\.\)]\s*/', '', $heading) ?? $heading);
        $headingSlug = Str::limit(Str::slug($cleanHeading) ?: "phan-{$index}", 45, '');

        $fileName = $role === 'featured'
            ? "{$keywordSlug}-anh-dai-dien.webp"
            : "{$keywordSlug}-{$headingSlug}-{$index}.webp";

        $prompt = trim(implode(' ', [
            "A realistic commercial editorial photograph for a {$industryName} article about {$keyword}.",
            "Visual concept illustrating: {$cleanHeading} ({$subjectHint}).",
            "Setting: {$visualConfig['environment']}.",
            "Style: {$visualConfig['style']}, 16:9 aspect ratio, anatomically accurate, natural colors.",
            'STRICT NEGATIVE RULES: NO text overlay, NO letters, NO words, NO logos, NO watermarks, NO distorted hands or faces, NO fake certificates.',
        ]));

        $altText = $role === 'featured'
            ? "Hình minh họa tổng quan về {$keyword}: {$cleanHeading}"
            : "Hình minh họa {$cleanHeading} trong bài viết {$keyword}";

        $imageTitle = Str::limit("{$cleanHeading} - {$keyword}", 90, '');
        $caption = "Hình minh họa: {$cleanHeading}";

        return [
            'role' => $role,
            'position_index' => $index,
            'target_heading' => $cleanHeading,
            'purpose' => $role === 'featured'
                ? 'Thu hút sự chú ý và thể hiện chủ đề chính của bài viết'
                : "Minh họa trực quan cho phần nội dung: {$cleanHeading}",
            'prompt' => $prompt,
            'seo_filename' => $fileName,
            'alt_text' => $altText,
            'image_title' => $imageTitle,
            'caption' => $caption,
            'is_illustrative_only' => true,
        ];
    }

    /**
     * Tính toán số lượng ảnh minh họa trong bài (Inline Images) tối ưu theo độ dài bài viết & số thẻ H2:
     * - Bài ngắn (800 - 1.200 từ): 2 ảnh inline
     * - Bài tiêu chuẩn (1.200 - 1.800 từ): 3 - 4 ảnh inline
     * - Bài chuyên sâu (1.800 - 2.500 từ): 4 - 5 ảnh inline
     * - Bài Pillar (2.500 - 3.500+ từ): 6 - 7 ảnh inline
     * Đồng thời giới hạn không vượt quá số mục H2 của bài để tránh nhồi 2 ảnh vào cùng 1 mục.
     */
    public function calculateOptimalInlineImageCount(int $wordCount, int $h2Count = 0, string $lengthTier = ''): int
    {
        $tierDefaults = [
            'short' => 2,
            'standard' => 3,
            'comprehensive' => 5,
            'advanced' => 6,
        ];

        if ($wordCount > 0) {
            $byWords = (int) round($wordCount / 400);
            $target = max(2, min(7, $byWords));
        } elseif (isset($tierDefaults[$lengthTier])) {
            $target = $tierDefaults[$lengthTier];
        } else {
            $target = (int) config('ai.image_generation.default_inline_count', 3);
        }

        if ($h2Count >= 2) {
            // Tránh chèn ảnh vào mục FAQ cuối bài nếu có nhiều hơn 2 H2
            $usableH2 = $h2Count > 3 ? $h2Count - 1 : $h2Count;

            return max(2, min($usableH2, $target));
        }

        return max(2, min(7, $target));
    }

    /**
     * Phân bổ đều các ảnh inline vào danh sách H2 theo bước nhảy (step = M / N) để ảnh rải đều từ đầu đến cuối bài.
     *
     * @param  array<int, string>  $headings
     * @return array<int, array{h2_index: int, heading: string}>
     */
    public function selectDistributedHeadings(array $headings, int $inlineCount, string $keyword): array
    {
        $total = count($headings);
        if ($total === 0) {
            $fallback = [];
            for ($i = 0; $i < $inlineCount; $i++) {
                $fallback[] = [
                    'h2_index' => $i + 1,
                    'heading' => "Khía cạnh trọng tâm số ".($i + 1)." về {$keyword}",
                ];
            }

            return $fallback;
        }

        // Nếu H2 cuối cùng là FAQ và tổng số H2 > inlineCount, ưu tiên rải ảnh vào các H2 nội dung phía trước
        $usableTotal = $total;
        $lastHeading = $headings[$total - 1] ?? '';
        if ($total > $inlineCount && preg_match('/(faq|câu hỏi thường gặp|thắc mắc)/iu', $lastHeading)) {
            $usableTotal = max($inlineCount, $total - 1);
        }

        $selected = [];
        $usedIndices = [];
        for ($i = 0; $i < $inlineCount; $i++) {
            $idx = (int) floor(($i * $usableTotal) / max(1, $inlineCount));
            $idx = max(0, min($total - 1, $idx));

            while (in_array($idx, $usedIndices, true) && $idx < $total - 1) {
                $idx++;
            }
            $usedIndices[] = $idx;

            $selected[] = [
                'h2_index' => $idx + 1, // 1-based H2 index
                'heading' => $headings[$idx] ?? ("Khía cạnh trọng tâm số ".($i + 1)." về {$keyword}"),
            ];
        }

        return $selected;
    }

    /**
     * @return array<int, string>
     */
    protected function extractH2Headings(string $html): array
    {
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches);

        return array_values(array_filter(array_map(
            fn (string $heading) => trim(strip_tags($heading)),
            $matches[1] ?? []
        )));
    }

    /**
     * @return array<int, string>
     */
    protected function extractHeadings(string $html): array
    {
        preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $matches);

        return array_values(array_filter(array_map(
            fn (string $heading) => trim(strip_tags($heading)),
            $matches[1] ?? []
        )));
    }
}
