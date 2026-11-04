<?php

namespace App\Services\Brand;

use App\Models\BrandKnowledgeBase;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BrandKnowledgeService
{
    /**
     * Lấy danh sách hồ sơ tri thức thương hiệu theo Workspace và Website.
     *
     * @param  array{search?: ?string, tone_of_voice?: ?string}  $filters
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

            $cleanedData = $this->sanitizePayload($data);
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
            $cleanedData = $this->sanitizePayload($data);

            if (isset($cleanedData['is_default']) && $cleanedData['is_default']) {
                BrandKnowledgeBase::where('workspace_id', $kb->workspace_id)
                    ->where('website_id', $kb->website_id)
                    ->where('id', '!=', $kb->id)
                    ->update(['is_default' => false]);
            }

            $kb->update($cleanedData);

            if ($kb->is_default) {
                $website = $kb->website ?: Website::find($kb->website_id);
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
     * Xây dựng đoạn prompt nén chứa Brand Knowledge để inject vào AI writing agents.
     */
    public function buildPromptContext(int|Website $website, ?int $brandKnowledgeId = null): string
    {
        $websiteId = $website instanceof Website ? $website->id : $website;
        $workspaceId = $website instanceof Website ? $website->workspace_id : null;

        $query = BrandKnowledgeBase::where('website_id', $websiteId);
        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        if ($brandKnowledgeId) {
            $kb = (clone $query)->find($brandKnowledgeId);
        } else {
            $kb = (clone $query)->where('is_default', true)->first()
                ?: (clone $query)->orderByDesc('updated_at')->first();
        }

        if (! $kb) {
            return '';
        }

        $lines = [];
        $lines[] = '### BRAND KNOWLEDGE & VOICE INSTRUCTIONS:';
        $lines[] = '- Brand Name: '.$kb->brand_name;

        if (! empty($kb->tagline)) {
            $lines[] = '- Tagline / Slogan: '.$kb->tagline;
        }

        $lines[] = '- Tone of Voice: '.($kb->tone_of_voice ?: 'professional');

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
        $query = BrandKnowledgeBase::where('website_id', $websiteId);

        if ($brandKnowledgeId) {
            $kb = (clone $query)->find($brandKnowledgeId);
        } else {
            $kb = (clone $query)->where('is_default', true)->first()
                ?: (clone $query)->orderByDesc('updated_at')->first();
        }

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

    /**
     * Làm sạch và chuẩn hóa dữ liệu mảng trước khi ghi CSDL.
     */
    protected function sanitizePayload(array $data): array
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

        return $data;
    }

    /**
     * Đồng bộ tóm tắt thương hiệu vào bảng websites.
     */
    protected function syncWebsiteBrandVoiceSummary(Website $website, BrandKnowledgeBase $kb): void
    {
        $parts = [];
        $parts[] = 'Brand: '.$kb->brand_name;
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
