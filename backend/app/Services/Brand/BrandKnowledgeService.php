<?php

namespace App\Services\Brand;

use App\Models\BrandKnowledgeBase;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class BrandKnowledgeService
{
    protected static ?bool $hasExtendedColumns = null;

    public function __construct(
        protected ?IndustryProfileRegistry $industryRegistry = null
    ) {
        $this->industryRegistry = $industryRegistry ?? app(IndustryProfileRegistry::class);
    }

    /**
     * Lấy danh sách hồ sơ tri thức thương hiệu theo Workspace và Website.
     *
     * @param  array{search?: ?string, tone_of_voice?: ?string, industry_key?: ?string}  $filters
     */
    public function listForWebsite(Workspace $workspace, ?int $websiteId = null, array $filters = []): Collection
    {
        $query = BrandKnowledgeBase::where('workspace_id', $workspace->id)
            ->with(['website:id,name,domain,url']);

        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('brand_name', 'like', $search)
                    ->orWhere('tagline', 'like', $search)
                    ->orWhere('custom_rules', 'like', $search);
            });
        }

        if (! empty($filters['tone_of_voice'])) {
            $query->where('tone_of_voice', $filters['tone_of_voice']);
        }

        return $query->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Tìm hồ sơ tri thức thương hiệu theo ID thuộc Workspace.
     */
    public function getById(Workspace $workspace, int $id): ?BrandKnowledgeBase
    {
        return BrandKnowledgeBase::where('workspace_id', $workspace->id)
            ->with(['website:id,name,domain,url'])
            ->find($id);
    }

    /**
     * Lấy hồ sơ tri thức thương hiệu áp dụng cho Website (theo ID hoặc mặc định).
     */
    public function getForWebsite(Workspace $workspace, int $websiteId, ?int $brandKnowledgeId = null): ?BrandKnowledgeBase
    {
        $query = BrandKnowledgeBase::where('workspace_id', $workspace->id)
            ->where('website_id', $websiteId);

        if ($brandKnowledgeId) {
            $specific = (clone $query)->find($brandKnowledgeId);
            if ($specific) {
                return $specific;
            }
        }

        // Lấy bản ghi mặc định (is_default = true)
        $default = (clone $query)->where('is_default', true)->first();
        if ($default) {
            return $default;
        }

        // Fallback lấy bản ghi mới nhất của website
        return $query->orderByDesc('updated_at')->first();
    }

    /**
     * Tạo hồ sơ tri thức thương hiệu mới.
     */
    public function createBrandKnowledge(Workspace $workspace, int $websiteId, array $data, ?User $actor = null): BrandKnowledgeBase
    {
        /** @var Website|null $website */
        $website = Website::where('workspace_id', $workspace->id)->find($websiteId);
        if (! $website) {
            throw new InvalidArgumentException('Website không tồn tại hoặc không thuộc Workspace hiện tại.');
        }

        return DB::transaction(function () use ($workspace, $website, $data) {
            $hasExisting = BrandKnowledgeBase::where('workspace_id', $workspace->id)
                ->where('website_id', $website->id)
                ->exists();

            $isDefault = ! $hasExisting || (! empty($data['is_default']));

            if ($isDefault) {
                BrandKnowledgeBase::where('workspace_id', $workspace->id)
                    ->where('website_id', $website->id)
                    ->update(['is_default' => false]);
            }

            $cleanedData = $this->sanitizePayload($data, null, $website);
            $cleanedData['workspace_id'] = $workspace->id;
            $cleanedData['website_id'] = $website->id;
            $cleanedData['is_default'] = $isDefault;

            /** @var BrandKnowledgeBase $kb */
            $kb = BrandKnowledgeBase::create($cleanedData);

            if ($isDefault) {
                $this->syncWebsiteBrandVoiceSummary($website, $kb);
            }

            return $kb->load('website:id,name,domain,url');
        });
    }

    /**
     * Cập nhật hồ sơ tri thức thương hiệu.
     */
    public function updateBrandKnowledge(BrandKnowledgeBase $kb, array $data, ?User $actor = null): BrandKnowledgeBase
    {
        return DB::transaction(function () use ($kb, $data) {
            $website = $kb->website ?: Website::find($kb->website_id);
            $cleanedData = $this->sanitizePayload($data, $kb, $website);

            if (isset($cleanedData['is_default']) && $cleanedData['is_default']) {
                BrandKnowledgeBase::where('workspace_id', $kb->workspace_id)
                    ->where('website_id', $kb->website_id)
                    ->where('id', '!=', $kb->id)
                    ->update(['is_default' => false]);
            }

            $kb->update($cleanedData);

            if ($kb->is_default) {
                if ($website) {
                    $this->syncWebsiteBrandVoiceSummary($website, $kb);
                }
            }

            return $kb->fresh(['website:id,name,domain,url']);
        });
    }

    /**
     * Xóa hồ sơ tri thức thương hiệu.
     */
    public function deleteBrandKnowledge(BrandKnowledgeBase $kb): bool
    {
        return DB::transaction(function () use ($kb) {
            $websiteId = $kb->website_id;
            $workspaceId = $kb->workspace_id;
            $wasDefault = (bool) $kb->is_default;

            $deleted = $kb->delete();

            if ($deleted && $wasDefault) {
                /** @var BrandKnowledgeBase|null $nextDefault */
                $nextDefault = BrandKnowledgeBase::where('workspace_id', $workspaceId)
                    ->where('website_id', $websiteId)
                    ->orderByDesc('updated_at')
                    ->first();

                if ($nextDefault) {
                    $nextDefault->update(['is_default' => true]);
                    $website = Website::find($websiteId);
                    if ($website) {
                        $this->syncWebsiteBrandVoiceSummary($website, $nextDefault);
                    }
                } else {
                    DB::table('websites')
                        ->where('id', $websiteId)
                        ->update(['brand_voice_summary' => null]);
                }
            }

            return (bool) $deleted;
        });
    }

    /**
     * Đặt một hồ sơ làm mặc định cho Website.
     */
    public function setDefault(BrandKnowledgeBase $kb): BrandKnowledgeBase
    {
        return DB::transaction(function () use ($kb) {
            BrandKnowledgeBase::where('workspace_id', $kb->workspace_id)
                ->where('website_id', $kb->website_id)
                ->where('id', '!=', $kb->id)
                ->update(['is_default' => false]);

            $kb->update(['is_default' => true]);

            $website = $kb->website ?: Website::find($kb->website_id);
            if ($website) {
                $this->syncWebsiteBrandVoiceSummary($website, $kb);
            }

            return $kb->fresh(['website:id,name,domain,url']);
        });
    }

    /**
     * Trích xuất toàn bộ ngữ cảnh có cấu trúc của Doanh nghiệp (Business Context, Tier A Verified Data, Unverified Fields, Industry Profile).
     *
     * @return array<string, mixed>
     */
    public function extractStructuredContext(int|Website $website, ?int $brandKnowledgeId = null): array
    {
        $kb = $this->resolveKnowledgeBase($website, $brandKnowledgeId);
        if (! $kb) {
            $defaultIndustry = $this->industryRegistry->getProfile(IndustryProfileRegistry::INDUSTRY_OTHER);

            return [
                'has_profile' => false,
                'brand_knowledge_id' => null,
                'brand_name' => $website instanceof Website ? ($website->name ?? $website->domain ?? '') : '',
                'industry_key' => IndustryProfileRegistry::INDUSTRY_OTHER,
                'industry_profile' => $defaultIndustry,
                'business_model' => 'B2C',
                'business_profile' => [],
                'eeat_credentials' => [],
                'brand_orientation' => [],
                'tier_a_verified_facts' => [],
                'unverified_fields' => [
                    'expert_team',
                    'verified_qualifications',
                    'operating_license',
                    'verified_achievements',
                    'partners_certifications',
                    'sales_policy',
                    'warranty_policy',
                    'contact_info',
                ],
            ];
        }

        $bp = $kb->business_profile;
        $eeat = $kb->eeat_credentials;
        $orientation = $kb->brand_orientation;
        $industryKey = $kb->industry_key;
        $industryProfile = $kb->industry_profile;

        $verifiedFacts = [];
        $unverifiedFields = [];

        // Phân loại dữ liệu đã xác minh (Tier A) vs chưa xác minh
        if (! empty($kb->brand_name)) {
            $verifiedFacts['brand_name'] = $kb->brand_name;
        }
        if (! empty($bp['company_name'])) {
            $verifiedFacts['company_name'] = $bp['company_name'];
        }
        if (! empty($bp['service_area'])) {
            $verifiedFacts['service_area'] = $bp['service_area'];
        }
        if (! empty($kb->unique_selling_points)) {
            $verifiedFacts['unique_selling_points'] = $kb->unique_selling_points;
        }
        if (! empty($kb->product_catalog)) {
            $verifiedFacts['product_catalog'] = $kb->product_catalog;
        }

        $verifiableChecks = [
            'expert_team' => $eeat['expert_team'] ?? [],
            'verified_qualifications' => $eeat['verified_qualifications'] ?? [],
            'operating_license' => $eeat['operating_license'] ?? '',
            'verified_achievements' => $eeat['verified_achievements'] ?? [],
            'partners_certifications' => $eeat['partners_certifications'] ?? [],
            'reference_sources' => $eeat['reference_sources'] ?? [],
            'content_reviewer' => $eeat['content_reviewer'] ?? '',
            'sales_policy' => $bp['sales_policy'] ?? '',
            'warranty_policy' => $bp['warranty_policy'] ?? '',
            'contact_info' => $bp['contact_info'] ?? '',
            'company_history' => $bp['company_history'] ?? '',
        ];

        foreach ($verifiableChecks as $fieldKey => $val) {
            $hasValue = is_array($val) ? count(array_filter($val)) > 0 : trim((string) $val) !== '';
            if ($hasValue) {
                $verifiedFacts[$fieldKey] = $val;
            } else {
                $unverifiedFields[] = $fieldKey;
            }
        }

        return [
            'has_profile' => true,
            'brand_knowledge_id' => $kb->id,
            'brand_name' => $kb->brand_name,
            'tagline' => $kb->tagline,
            'tone_of_voice' => $kb->tone_of_voice ?: 'professional',
            'industry_key' => $industryKey,
            'industry_profile' => $industryProfile,
            'business_model' => $kb->business_model ?: ($bp['business_model'] ?? 'B2C'),
            'business_profile' => $bp,
            'eeat_credentials' => $eeat,
            'brand_orientation' => $orientation,
            'target_personas' => $kb->target_personas ?? [],
            'unique_selling_points' => $kb->unique_selling_points ?? [],
            'product_catalog' => $kb->product_catalog ?? [],
            'preferred_terms' => $kb->preferred_terms ?? [],
            'taboo_words' => $kb->taboo_words ?? [],
            'custom_rules' => $kb->custom_rules,
            'tier_a_verified_facts' => $verifiedFacts,
            'unverified_fields' => $unverifiedFields,
        ];
    }

    /**
     * Xây dựng đoạn prompt nén chứa Brand Knowledge, E-E-A-T & Industry Profile để inject vào AI writing agents.
     */
    public function buildPromptContext(int|Website $website, ?int $brandKnowledgeId = null): string
    {
        $kb = $this->resolveKnowledgeBase($website, $brandKnowledgeId);
        if (! $kb) {
            return '';
        }

        $bp = $kb->business_profile;
        $eeat = $kb->eeat_credentials;
        $orientation = $kb->brand_orientation;
        $industryKey = $kb->industry_key;

        $lines = [];
        $lines[] = '### BRAND KNOWLEDGE & VOICE INSTRUCTIONS:';
        $lines[] = '- Brand Name: '.$kb->brand_name;

        if (! empty($bp['company_name']) && $bp['company_name'] !== $kb->brand_name) {
            $lines[] = '- Legal / Company Name: '.$bp['company_name'];
        }
        if (! empty($bp['website_url'])) {
            $lines[] = '- Official Website: '.$bp['website_url'];
        }
        if (! empty($kb->tagline)) {
            $lines[] = '- Tagline / Slogan: '.$kb->tagline;
        }

        $lines[] = '- Business Model: '.($kb->business_model ?: ($bp['business_model'] ?? 'B2C'));
        if (! empty($bp['primary_industry'])) {
            $indStr = $bp['primary_industry'];
            if (! empty($bp['secondary_industry'])) {
                $indStr .= ' (Secondary: '.$bp['secondary_industry'].')';
            }
            $lines[] = '- Declared Industry: '.$indStr;
        }
        if (! empty($bp['country_market']) || ! empty($bp['service_area'])) {
            $lines[] = '- Market & Service Area: '.trim(($bp['country_market'] ?? 'Việt Nam').' - '.($bp['service_area'] ?? ''));
        }

        $lines[] = '- Tone of Voice: '.($kb->tone_of_voice ?: 'professional');
        if (! empty($orientation['writing_style'])) {
            $lines[] = '- Writing Style: '.$orientation['writing_style'];
        }
        if (! empty($orientation['pronouns_addressing'])) {
            $lines[] = '- Pronouns / Way of Addressing Reader: '.$orientation['pronouns_addressing'];
        }
        if (! empty($orientation['brand_positioning'])) {
            $lines[] = '- Brand Positioning: '.$orientation['brand_positioning'];
        }

        if (! empty($bp['company_intro'])) {
            $lines[] = '- Business Overview: '.$bp['company_intro'];
        }
        if (! empty($bp['problems_solved'])) {
            $lines[] = '- Core Customer Problems Solved: '.$bp['problems_solved'];
        }
        if (! empty($bp['competitive_differentiation'])) {
            $lines[] = '- Competitive Differentiation: '.$bp['competitive_differentiation'];
        }

        if (! empty($kb->target_personas) && is_array($kb->target_personas)) {
            $personas = implode(', ', array_filter($kb->target_personas));
            if ($personas !== '') {
                $lines[] = '- Target Audience / Personas: '.$personas;
            }
        }

        if (! empty($kb->unique_selling_points) && is_array($kb->unique_selling_points)) {
            $usps = implode('; ', array_filter($kb->unique_selling_points));
            if ($usps !== '') {
                $lines[] = '- Unique Selling Points (USPs): '.$usps;
            }
        }

        if (! empty($kb->product_catalog) && is_array($kb->product_catalog)) {
            $products = [];
            foreach ($kb->product_catalog as $prod) {
                if (is_array($prod) && ! empty($prod['name'])) {
                    $itemStr = $prod['name'];
                    if (! empty($prod['description'])) {
                        $itemStr .= ' ('.$prod['description'].')';
                    }
                    if (! empty($prod['url'])) {
                        $itemStr .= ' ['.$prod['url'].']';
                    }
                    $products[] = $itemStr;
                } elseif (is_string($prod) && trim($prod) !== '') {
                    $products[] = trim($prod);
                }
            }
            if (! empty($products)) {
                $lines[] = '- Products & Services to Reference: '.implode(', ', $products);
            }
        }

        if (! empty($bp['sales_policy'])) {
            $lines[] = '- Verified Sales Policy: '.$bp['sales_policy'];
        }
        if (! empty($bp['warranty_policy'])) {
            $lines[] = '- Verified Warranty Policy: '.$bp['warranty_policy'];
        }
        if (! empty($bp['contact_info'])) {
            $lines[] = '- Verified Contact Info: '.$bp['contact_info'];
        }
        if (! empty($orientation['priority_cta'])) {
            $lines[] = '- Priority CTA: '.$orientation['priority_cta'];
        }

        // E-E-A-T Verified Credentials (Tier A Data)
        $lines[] = '';
        $lines[] = '### TIER A VERIFIED E-E-A-T CREDENTIALS (ONLY USE WHAT IS LISTED BELOW - NEVER FABRICATE):';
        $hasAnyEeat = false;
        if (! empty($eeat['expert_team']) && is_array($eeat['expert_team'])) {
            $experts = implode('; ', array_filter($eeat['expert_team']));
            if ($experts !== '') {
                $lines[] = '- Verified Expert Team: '.$experts;
                $hasAnyEeat = true;
            }
        }
        if (! empty($eeat['verified_qualifications']) && is_array($eeat['verified_qualifications'])) {
            $quals = implode('; ', array_filter($eeat['verified_qualifications']));
            if ($quals !== '') {
                $lines[] = '- Verified Qualifications & Degrees: '.$quals;
                $hasAnyEeat = true;
            }
        }
        if (! empty($eeat['operating_license'])) {
            $lines[] = '- Verified Operating License: '.$eeat['operating_license'];
            $hasAnyEeat = true;
        }
        if (! empty($eeat['verified_achievements']) && is_array($eeat['verified_achievements'])) {
            $ach = implode('; ', array_filter($eeat['verified_achievements']));
            if ($ach !== '') {
                $lines[] = '- Verified Achievements & Awards: '.$ach;
                $hasAnyEeat = true;
            }
        }
        if (! empty($eeat['partners_certifications']) && is_array($eeat['partners_certifications'])) {
            $part = implode('; ', array_filter($eeat['partners_certifications']));
            if ($part !== '') {
                $lines[] = '- Verified Partners & Certifications: '.$part;
                $hasAnyEeat = true;
            }
        }
        if (! empty($eeat['reference_sources']) && is_array($eeat['reference_sources'])) {
            $refs = implode('; ', array_filter($eeat['reference_sources']));
            if ($refs !== '') {
                $lines[] = '- Authoritative Reference Sources: '.$refs;
                $hasAnyEeat = true;
            }
        }
        if (! empty($eeat['content_reviewer'])) {
            $lines[] = '- Designated Content Reviewer: '.$eeat['content_reviewer'];
            $hasAnyEeat = true;
        }

        if (! $hasAnyEeat) {
            $lines[] = '- Notice: Doanh nghiệp chưa khai báo bằng cấp, giấy phép, giải thưởng hoặc tên chuyên gia cụ thể. TUYỆT ĐỐI KHÔNG tự bịa đặt tên bác sĩ/luật sư/chuyên gia, số giấy phép, giải thưởng hoặc số năm kinh nghiệm chưa có trong hồ sơ.';
        } else {
            $lines[] = '- Strict Anti-Hallucination Rule: Không tự tạo hoặc suy diễn bất kỳ bằng cấp, giấy phép, giải thưởng hay số liệu nào nằm ngoài danh sách đã xác minh ở trên.';
        }

        if (! empty($kb->preferred_terms) && is_array($kb->preferred_terms)) {
            $preferred = implode(', ', array_filter($kb->preferred_terms));
            if ($preferred !== '') {
                $lines[] = '- Preferred Terms (Use Naturally): '.$preferred;
            }
        }

        if (! empty($kb->taboo_words) && is_array($kb->taboo_words)) {
            $taboo = implode(', ', array_filter($kb->taboo_words));
            if ($taboo !== '') {
                $lines[] = '- STRICT TABOO WORDS (NEVER USE IN CONTENT): '.$taboo;
            }
        }

        if (! empty($kb->custom_rules)) {
            $lines[] = '- Custom Editorial Rules: '.trim($kb->custom_rules);
        }

        // Inject Industry Profile Specific Instructions
        $lines[] = '';
        $lines[] = $this->industryRegistry->buildIndustryPromptInstructions($industryKey);

        return implode("\n", $lines);
    }

    /**
     * Nhúng tri thức thương hiệu vào Prompt văn bản.
     */
    public function injectIntoPrompt(
        string $prompt,
        int|Website $website,
        ?int $brandKnowledgeId = null,
        string $placeholder = '{{brand_context}}'
    ): string {
        $brandContext = $this->buildPromptContext($website, $brandKnowledgeId);

        if (str_contains($prompt, $placeholder)) {
            return str_replace($placeholder, $brandContext, $prompt);
        }

        if ($brandContext === '') {
            return $prompt;
        }

        return rtrim($prompt)."\n\n".$brandContext;
    }

    /**
     * Kiểm tra nội dung bài viết xem có vi phạm danh sách từ ngữ cấm kỵ (Taboo Words) hay không.
     *
     * @return array{clean: bool, violations: array<string, int>, taboo_count: int}
     */
    public function validateContentAgainstTaboo(string $content, int $websiteId, ?int $brandKnowledgeId = null): array
    {
        $kb = $this->resolveKnowledgeBase($websiteId, $brandKnowledgeId);

        if (! $kb || empty($kb->taboo_words) || ! is_array($kb->taboo_words)) {
            return [
                'clean' => true,
                'violations' => [],
                'taboo_count' => 0,
            ];
        }

        $violations = [];
        $lowerContent = mb_strtolower($content, 'UTF-8');

        foreach ($kb->taboo_words as $taboo) {
            if (! is_string($taboo)) {
                continue;
            }
            $cleanTaboo = trim($taboo);
            if ($cleanTaboo === '') {
                continue;
            }

            $cleanTabooLower = mb_strtolower($cleanTaboo, 'UTF-8');
            $occurrences = mb_substr_count($lowerContent, $cleanTabooLower, 'UTF-8');

            if ($occurrences > 0) {
                $violations[$cleanTaboo] = $occurrences;
            }
        }

        return [
            'clean' => count($violations) === 0,
            'violations' => $violations,
            'taboo_count' => array_sum($violations),
        ];
    }

    protected function resolveKnowledgeBase(int|Website $website, ?int $brandKnowledgeId = null): ?BrandKnowledgeBase
    {
        $websiteId = $website instanceof Website ? $website->id : $website;
        $workspaceId = $website instanceof Website ? $website->workspace_id : null;

        $query = BrandKnowledgeBase::where('website_id', $websiteId);
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        if ($brandKnowledgeId) {
            $specific = (clone $query)->find($brandKnowledgeId);
            if ($specific) {
                return $specific;
            }
        }

        return (clone $query)->where('is_default', true)->first()
            ?: (clone $query)->orderByDesc('updated_at')->first();
    }

    /**
     * Làm sạch và chuẩn hóa dữ liệu mảng trước khi ghi CSDL.
     * Tự động phân loại Industry Profile và đảm bảo tương thích ngược với cả DB chưa chạy migration mới.
     */
    protected function sanitizePayload(array $data, ?BrandKnowledgeBase $existing = null, ?Website $website = null): array
    {
        $arrayFields = ['target_personas', 'unique_selling_points', 'taboo_words', 'preferred_terms'];

        foreach ($arrayFields as $field) {
            if (isset($data[$field])) {
                if (is_string($data[$field])) {
                    $items = array_map('trim', explode(',', $data[$field]));
                    $data[$field] = array_values(array_filter($items, fn ($item) => $item !== ''));
                } elseif (is_array($data[$field])) {
                    $items = array_map(fn ($item) => is_string($item) ? trim($item) : $item, $data[$field]);
                    $data[$field] = array_values(array_filter($items, fn ($item) => is_string($item) ? $item !== '' : ! empty($item)));
                }
            }
        }

        if (isset($data['product_catalog']) && is_array($data['product_catalog'])) {
            $cleanedCatalog = [];
            foreach ($data['product_catalog'] as $product) {
                if (is_array($product) && ! empty($product['name'])) {
                    $cleanedCatalog[] = [
                        'name' => trim((string) $product['name']),
                        'description' => isset($product['description']) ? trim((string) $product['description']) : null,
                        'url' => isset($product['url']) ? trim((string) $product['url']) : null,
                    ];
                } elseif (is_string($product) && trim($product) !== '') {
                    $cleanedCatalog[] = [
                        'name' => trim($product),
                        'description' => null,
                        'url' => null,
                    ];
                }
            }
            $data['product_catalog'] = $cleanedCatalog;
        }

        // Chuẩn hóa Business Profile (3.1 & 3.2)
        $existingBp = $existing ? $existing->business_profile : [];
        $incomingBp = isset($data['business_profile']) && is_array($data['business_profile']) ? $data['business_profile'] : [];
        $mergedBp = array_merge($existingBp, $incomingBp);

        $brandName = $data['brand_name'] ?? $existing?->brand_name ?? '';
        $mergedBp['brand_name'] = $mergedBp['brand_name'] ?? $brandName;
        $mergedBp['company_name'] = ! empty($mergedBp['company_name']) ? trim((string) $mergedBp['company_name']) : $brandName;
        $mergedBp['website_url'] = ! empty($mergedBp['website_url'])
            ? trim((string) $mergedBp['website_url'])
            : ($website?->url ?? $website?->domain ?? '');
        $mergedBp['country_market'] = ! empty($mergedBp['country_market']) ? trim((string) $mergedBp['country_market']) : 'Việt Nam';
        $mergedBp['content_language'] = ! empty($mergedBp['content_language']) ? trim((string) $mergedBp['content_language']) : 'vi';
        $mergedBp['business_model'] = ! empty($data['business_model'])
            ? trim((string) $data['business_model'])
            : (! empty($mergedBp['business_model']) ? trim((string) $mergedBp['business_model']) : 'B2C');

        // Chuẩn hóa E-E-A-T Credentials (3.3)
        $existingEeat = $existing ? $existing->eeat_credentials : [];
        $incomingEeat = isset($data['eeat_credentials']) && is_array($data['eeat_credentials']) ? $data['eeat_credentials'] : [];
        $mergedEeat = array_merge($existingEeat, $incomingEeat);
        foreach (['expert_team', 'verified_qualifications', 'verified_achievements', 'partners_certifications', 'reference_sources'] as $eeatArrField) {
            if (isset($mergedEeat[$eeatArrField])) {
                if (is_string($mergedEeat[$eeatArrField])) {
                    $mergedEeat[$eeatArrField] = array_values(array_filter(array_map('trim', explode("\n", $mergedEeat[$eeatArrField]))));
                } elseif (is_array($mergedEeat[$eeatArrField])) {
                    $mergedEeat[$eeatArrField] = array_values(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $mergedEeat[$eeatArrField])));
                }
            } else {
                $mergedEeat[$eeatArrField] = [];
            }
        }

        // Chuẩn hóa Brand Orientation (3.4)
        $existingOrientation = $existing ? $existing->brand_orientation : [];
        $incomingOrientation = isset($data['brand_orientation']) && is_array($data['brand_orientation']) ? $data['brand_orientation'] : [];
        $mergedOrientation = array_merge($existingOrientation, $incomingOrientation);
        $mergedOrientation['writing_style'] = ! empty($mergedOrientation['writing_style'])
            ? trim((string) $mergedOrientation['writing_style'])
            : ($data['tone_of_voice'] ?? $existing?->tone_of_voice ?? 'professional');
        $mergedOrientation['brand_voice'] = ! empty($mergedOrientation['brand_voice'])
            ? trim((string) $mergedOrientation['brand_voice'])
            : ($data['tone_of_voice'] ?? $existing?->tone_of_voice ?? 'professional');

        // Tự động phân loại Industry Profile (3.5)
        $explicitIndustryKey = $data['industry_key'] ?? ($existing ? $existing->industry_key : null);
        $detectionSignals = [
            'industry_key' => $explicitIndustryKey,
            'primary_industry' => $mergedBp['primary_industry'] ?? '',
            'secondary_industry' => $mergedBp['secondary_industry'] ?? '',
            'brand_name' => $brandName,
            'tagline' => $data['tagline'] ?? $existing?->tagline ?? '',
            'company_intro' => $mergedBp['company_intro'] ?? '',
            'problems_solved' => $mergedBp['problems_solved'] ?? '',
            'product_catalog' => $data['product_catalog'] ?? $existing?->product_catalog ?? [],
            'unique_selling_points' => $data['unique_selling_points'] ?? $existing?->unique_selling_points ?? [],
        ];

        // Nếu người dùng truyền primary_industry mới hoặc chưa chỉ định industry_key cố định, tự động phân loại
        if (! empty($incomingBp['primary_industry']) && empty($data['industry_key'])) {
            unset($detectionSignals['industry_key']);
        }
        $resolvedIndustryKey = $this->industryRegistry->detectIndustry($detectionSignals);
        $baseIndustryProfile = $this->industryRegistry->getProfile($resolvedIndustryKey);
        $customIndustryOverrides = isset($data['industry_profile']) && is_array($data['industry_profile'])
            ? $data['industry_profile']
            : [];
        $resolvedIndustryProfile = array_replace_recursive($baseIndustryProfile, $customIndustryOverrides);

        $extendedEnvelope = [
            'industry_key' => $resolvedIndustryKey,
            'business_model' => $mergedBp['business_model'],
            'business_profile' => $mergedBp,
            'eeat_credentials' => $mergedEeat,
            'brand_orientation' => $mergedOrientation,
            'industry_profile' => $resolvedIndustryProfile,
        ];

        $cleanCustomRules = isset($data['custom_rules'])
            ? $this->stripEnvelope((string) $data['custom_rules'])
            : ($existing ? ($existing->custom_rules ?? '') : '');

        if ($this->supportsExtendedColumns()) {
            $data['custom_rules'] = $cleanCustomRules !== '' ? $cleanCustomRules : null;
            $data['industry_key'] = $resolvedIndustryKey;
            $data['business_model'] = $mergedBp['business_model'];
            $data['business_profile'] = $mergedBp;
            $data['eeat_credentials'] = $mergedEeat;
            $data['brand_orientation'] = $mergedOrientation;
            $data['industry_profile'] = $resolvedIndustryProfile;
        } else {
            // Lưu an toàn vào custom_rules envelope nếu CSDL chưa chạy migration cột mới
            $encodedEnvelope = base64_encode((string) json_encode($extendedEnvelope, JSON_UNESCAPED_UNICODE));
            $data['custom_rules'] = trim($cleanCustomRules)
                .BrandKnowledgeBase::PROFILE_ENVELOPE_MARKER
                .$encodedEnvelope
                .BrandKnowledgeBase::PROFILE_ENVELOPE_END;
            unset(
                $data['industry_key'],
                $data['business_model'],
                $data['business_profile'],
                $data['eeat_credentials'],
                $data['brand_orientation'],
                $data['industry_profile']
            );
        }

        return $data;
    }

    protected function stripEnvelope(string $rawRules): string
    {
        $startPos = strpos($rawRules, BrandKnowledgeBase::PROFILE_ENVELOPE_MARKER);
        if ($startPos === false) {
            return trim($rawRules);
        }

        return trim(substr($rawRules, 0, $startPos));
    }

    protected function supportsExtendedColumns(): bool
    {
        if (self::$hasExtendedColumns !== null) {
            return self::$hasExtendedColumns;
        }

        try {
            self::$hasExtendedColumns = Schema::hasColumn('brand_knowledge_bases', 'business_profile');
        } catch (Throwable) {
            self::$hasExtendedColumns = false;
        }

        return self::$hasExtendedColumns;
    }

    /**
     * Đồng bộ tóm tắt thương hiệu vào bảng websites.
     */
    protected function syncWebsiteBrandVoiceSummary(Website $website, BrandKnowledgeBase $kb): void
    {
        $parts = [];
        $parts[] = 'Brand: '.$kb->brand_name;
        $parts[] = 'Industry: '.$kb->industry_key;
        $parts[] = 'Model: '.$kb->business_model;
        $parts[] = 'Tone: '.($kb->tone_of_voice ?: 'professional');

        if (! empty($kb->unique_selling_points) && is_array($kb->unique_selling_points)) {
            $parts[] = 'USPs: '.implode(', ', array_slice($kb->unique_selling_points, 0, 3));
        }

        $summary = implode(' | ', $parts);

        // Cập nhật trực tiếp vào CSDL và model để tránh bị lọc bởi $fillable của Website
        DB::table('websites')
            ->where('id', $website->id)
            ->update([
                'brand_voice_summary' => $summary,
            ]);

        $website->forceFill([
            'brand_voice_summary' => $summary,
        ]);
    }
}
