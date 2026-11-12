<?php

namespace App\Services\SEO;

use App\Models\Article;
use App\Models\Website;
use App\Services\Brand\BrandKnowledgeService;
use Illuminate\Support\Str;

class SchemaBuilderService
{
    public function __construct(
        protected BrandKnowledgeService $brandKnowledgeService
    ) {}

    /**
     * Sinh các đối tượng Schema.org JSON-LD hợp lệ dựa trên dữ liệu thực sự hiển thị trong bài viết (Section 9.4).
     * Tuyệt đối không tạo Schema sai sự thật hoặc chứa thông tin không hiển thị trong bài.
     *
     * @param  array<string, mixed>  $strategyAnalysis
     * @return array{types: array<int, string>, json_ld: array<int, array<string, mixed>>}
     */
    public function buildForArticle(Article $article, array $strategyAnalysis = []): array
    {
        $website = $article->website ?: ($article->website_id ? Website::find($article->website_id) : null);
        $brandKnowledgeId = (int) (
            data_get($article->pipeline_status, 'content_brief.brand_knowledge_id')
            ?: data_get($article->outline, 'content_brief.brand_knowledge_id')
            ?: 0
        ) ?: null;

        $brandContext = $website
            ? $this->brandKnowledgeService->extractStructuredContext($website, $brandKnowledgeId)
            : [];

        $baseUrl = $website
            ? rtrim($website->url ?: ('https://'.$website->domain), '/')
            : 'https://example.com';

        $articleSlug = $article->slug ?: Str::slug((string) ($article->title ?: $article->focus_keyword));
        $articleUrl = $article->wordpress_post_url ?: ($article->canonical_url ?: ($baseUrl.'/'.ltrim($articleSlug, '/')));
        $brandName = (string) ($brandContext['brand_name'] ?? $website?->name ?? 'Brand');
        $companyName = (string) ($brandContext['business_profile']['company_name'] ?? $brandName);
        $plainContent = trim(strip_tags((string) $article->content));

        $contentType = (string) (
            data_get($strategyAnalysis, 'step_4_content_strategy.content_type')
            ?: data_get($article->outline, 'content_type')
            ?: 'informational_article'
        );

        $graphs = [];
        $schemaTypes = [];

        // 1. Organization Schema (Chỉ sinh khi có tên thương hiệu / website xác thực)
        $orgSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $baseUrl.'/#organization',
            'name' => $companyName ?: $brandName,
            'url' => $baseUrl,
        ];
        if (! empty($brandContext['business_profile']['contact_info'])) {
            $orgSchema['description'] = (string) ($brandContext['business_profile']['company_intro'] ?: $brandContext['tagline'] ?? '');
        }
        $graphs[] = array_filter($orgSchema, fn ($v) => $v !== '');
        $schemaTypes[] = 'Organization';

        // 2. BreadcrumbList Schema
        $graphs[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Trang chủ',
                    'item' => $baseUrl,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => (string) ($article->title ?: $article->focus_keyword),
                    'item' => $articleUrl,
                ],
            ],
        ];
        $schemaTypes[] = 'BreadcrumbList';

        // 3. Article / BlogPosting Schema
        $primaryArticleSchemaType = in_array($contentType, ['pillar_page', 'informational_article', 'comparison_article', 'case_study'], true)
            ? 'Article'
            : 'BlogPosting';

        $articleSchema = [
            '@context' => 'https://schema.org',
            '@type' => $primaryArticleSchemaType,
            'headline' => Str::limit((string) ($article->seo_title ?: $article->title ?: $article->focus_keyword), 110, ''),
            'description' => (string) ($article->seo_description ?: $article->excerpt ?: Str::limit($plainContent, 155)),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $articleUrl,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $brandName,
                'url' => $baseUrl,
            ],
            'datePublished' => ($article->published_at ?: $article->created_at ?: now())->toIso8601String(),
            'dateModified' => ($article->updated_at ?: now())->toIso8601String(),
        ];

        // Chỉ gắn Author/Reviewer cụ thể nếu có trong Tier A Verified Credentials
        $verifiedReviewer = trim((string) ($brandContext['eeat_credentials']['content_reviewer'] ?? ''));
        if ($verifiedReviewer !== '') {
            $articleSchema['author'] = [
                '@type' => 'Organization',
                'name' => $verifiedReviewer.' - '.$brandName,
            ];
        } else {
            $articleSchema['author'] = [
                '@type' => 'Organization',
                'name' => 'Ban Biên Tập '.$brandName,
            ];
        }

        if (! empty($article->featured_image_url)) {
            $articleSchema['image'] = [$article->featured_image_url];
        }

        $graphs[] = $articleSchema;
        $schemaTypes[] = $primaryArticleSchemaType;

        // 4. FAQPage Schema (Chỉ tạo khi bài viết thực sự có danh sách câu hỏi & câu trả lời hiển thị)
        $faqs = is_array($article->faqs) ? $article->faqs : [];
        $validFaqEntities = [];
        foreach ($faqs as $faq) {
            $q = trim(strip_tags((string) ($faq['question'] ?? $faq['q'] ?? '')));
            $a = trim(strip_tags((string) ($faq['answer'] ?? $faq['a'] ?? '')));
            if ($q !== '' && $a !== '') {
                $validFaqEntities[] = [
                    '@type' => 'Question',
                    'name' => $q,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $a,
                    ],
                ];
            }
        }
        if (count($validFaqEntities) > 0) {
            $graphs[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $validFaqEntities,
            ];
            $schemaTypes[] = 'FAQPage';
        }

        // 5. Service Schema (Chỉ tạo khi bài viết thuộc loại dịch vụ hoặc có nhắc đến dịch vụ xác thực của doanh nghiệp)
        $promotedService = trim((string) (
            data_get($strategyAnalysis, 'step_1_business_context.promoted_product_service')
            ?: data_get($article->outline, 'content_brief.target_product_service')
            ?: ''
        ));
        if (in_array($contentType, ['service_landing_page', 'commercial_article', 'transactional_article', 'local_seo_article'], true) || $promotedService !== '') {
            $serviceName = $promotedService !== '' ? $promotedService : (string) ($article->focus_keyword ?: $article->title);
            if ($serviceName !== '' && mb_stripos($plainContent, $serviceName, 0, 'UTF-8') !== false) {
                $serviceSchema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Service',
                    'name' => $serviceName,
                    'provider' => [
                        '@type' => 'Organization',
                        'name' => $brandName,
                        'url' => $baseUrl,
                    ],
                    'description' => (string) ($article->seo_description ?: Str::limit($plainContent, 160)),
                ];
                $areaServed = trim((string) (
                    data_get($strategyAnalysis, 'step_1_business_context.market_and_area')
                    ?: ($brandContext['business_profile']['service_area'] ?? '')
                ));
                if ($areaServed !== '') {
                    $serviceSchema['areaServed'] = $areaServed;
                }
                $graphs[] = $serviceSchema;
                $schemaTypes[] = 'Service';
            }
        }

        // 6. Product Schema (Chỉ tạo khi Content Type là Product Review / Product Description và không tự bịa rating/giá giả)
        if (in_array($contentType, ['product_review', 'product_description'], true)) {
            $productName = $promotedService !== '' ? $promotedService : (string) ($article->focus_keyword ?: $article->title);
            $graphs[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $productName,
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $brandName,
                ],
                'description' => (string) ($article->seo_description ?: Str::limit($plainContent, 160)),
            ];
            $schemaTypes[] = 'Product';
        }

        // 7. LocalBusiness Schema (Chỉ tạo khi là bài Local SEO hoặc doanh nghiệp đã khai báo địa điểm phục vụ/liên hệ)
        $localArea = trim((string) (
            data_get($strategyAnalysis, 'step_2_keyword_analysis.local_seo_modifier')
            ?: ($brandContext['business_profile']['service_area'] ?? '')
        ));
        if (($contentType === 'local_seo_article' || ! empty($brandContext['business_profile']['contact_info'])) && $localArea !== '') {
            $localSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                'name' => $brandName,
                'url' => $baseUrl,
                'areaServed' => $localArea,
            ];
            if (! empty($brandContext['business_profile']['contact_info'])) {
                $localSchema['address'] = (string) $brandContext['business_profile']['contact_info'];
            }
            $graphs[] = $localSchema;
            $schemaTypes[] = 'LocalBusiness';
        }

        return [
            'types' => array_values(array_unique($schemaTypes)),
            'json_ld' => $graphs,
        ];
    }
}
