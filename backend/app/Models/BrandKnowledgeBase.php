<?php

namespace App\Models;

use App\Services\Brand\IndustryProfileRegistry;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandKnowledgeBase extends Model
{
    use BelongsToWorkspace, HasFactory;

    public const PROFILE_ENVELOPE_MARKER = "\n<!-- AURASEO_EXTENDED_PROFILE:";
    public const PROFILE_ENVELOPE_END = ":END_AURASEO_EXTENDED_PROFILE -->";

    protected $table = 'brand_knowledge_bases';

    protected $fillable = [
        'workspace_id',
        'website_id',
        'brand_name',
        'tagline',
        'tone_of_voice',
        'industry_key',
        'business_model',
        'target_personas',
        'unique_selling_points',
        'product_catalog',
        'taboo_words',
        'preferred_terms',
        'custom_rules',
        'business_profile',
        'eeat_credentials',
        'brand_orientation',
        'industry_profile',
        'is_default',
    ];

    protected $casts = [
        'target_personas' => 'array',
        'unique_selling_points' => 'array',
        'product_catalog' => 'array',
        'taboo_words' => 'array',
        'preferred_terms' => 'array',
        'business_profile' => 'array',
        'eeat_credentials' => 'array',
        'brand_orientation' => 'array',
        'industry_profile' => 'array',
        'is_default' => 'boolean',
    ];

    protected $appends = [
        'industry_key',
        'business_model',
        'business_profile',
        'eeat_credentials',
        'brand_orientation',
        'industry_profile',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'brand_knowledge_id');
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForWebsite(Builder $query, int $websiteId): Builder
    {
        return $query->where('website_id', $websiteId);
    }

    /**
     * Extract embedded extended profile JSON from custom_rules when native DB columns are absent.
     *
     * @return array<string, mixed>
     */
    public function getEmbeddedProfileEnvelope(): array
    {
        $rawRules = (string) ($this->attributes['custom_rules'] ?? '');
        $startPos = strpos($rawRules, self::PROFILE_ENVELOPE_MARKER);
        if ($startPos === false) {
            return [];
        }

        $jsonStart = $startPos + strlen(self::PROFILE_ENVELOPE_MARKER);
        $endPos = strpos($rawRules, self::PROFILE_ENVELOPE_END, $jsonStart);
        if ($endPos === false) {
            return [];
        }

        $encoded = substr($rawRules, $jsonStart, $endPos - $jsonStart);
        $decoded = json_decode(base64_decode($encoded, true) ?: $encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Return clean custom_rules without the embedded profile envelope.
     */
    public function getCustomRulesAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $startPos = strpos($value, self::PROFILE_ENVELOPE_MARKER);
        if ($startPos === false) {
            return $value;
        }

        $clean = trim(substr($value, 0, $startPos));

        return $clean !== '' ? $clean : null;
    }

    public function getIndustryKeyAttribute($value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $envelope = $this->getEmbeddedProfileEnvelope();
        if (! empty($envelope['industry_key']) && is_string($envelope['industry_key'])) {
            return $envelope['industry_key'];
        }

        $bp = $this->getBusinessProfileAttribute($this->attributes['business_profile'] ?? null);
        if (! empty($bp['primary_industry']) && is_string($bp['primary_industry'])) {
            return app(IndustryProfileRegistry::class)->detectIndustry(['primary_industry' => $bp['primary_industry']]);
        }

        return app(IndustryProfileRegistry::class)->detectIndustry([
            'brand_name' => $this->attributes['brand_name'] ?? '',
            'tagline' => $this->attributes['tagline'] ?? '',
            'product_catalog' => $this->product_catalog ?? [],
            'unique_selling_points' => $this->unique_selling_points ?? [],
        ]);
    }

    public function getBusinessModelAttribute($value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $envelope = $this->getEmbeddedProfileEnvelope();
        if (! empty($envelope['business_model']) && is_string($envelope['business_model'])) {
            return $envelope['business_model'];
        }

        $bp = $this->getBusinessProfileAttribute($this->attributes['business_profile'] ?? null);

        return (string) ($bp['business_model'] ?? 'B2C');
    }

    public function getBusinessProfileAttribute($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        } elseif (is_array($value) && ! empty($value)) {
            return $value;
        }

        $envelope = $this->getEmbeddedProfileEnvelope();
        if (! empty($envelope['business_profile']) && is_array($envelope['business_profile'])) {
            return $envelope['business_profile'];
        }

        return [
            'company_name' => $this->attributes['brand_name'] ?? '',
            'brand_name' => $this->attributes['brand_name'] ?? '',
            'website_url' => $this->relationLoaded('website') && $this->website ? ($this->website->url ?? $this->website->domain ?? '') : '',
            'primary_industry' => '',
            'secondary_industry' => '',
            'country_market' => 'Việt Nam',
            'service_area' => 'Toàn quốc',
            'content_language' => 'vi',
            'business_model' => 'B2C',
            'company_intro' => $this->attributes['tagline'] ?? '',
            'company_history' => '',
            'customer_locations' => '',
            'problems_solved' => '',
            'competitive_differentiation' => '',
            'sales_policy' => '',
            'warranty_policy' => '',
            'contact_info' => '',
        ];
    }

    public function getEeatCredentialsAttribute($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        } elseif (is_array($value) && ! empty($value)) {
            return $value;
        }

        $envelope = $this->getEmbeddedProfileEnvelope();
        if (! empty($envelope['eeat_credentials']) && is_array($envelope['eeat_credentials'])) {
            return $envelope['eeat_credentials'];
        }

        return [
            'expert_team' => [],
            'verified_qualifications' => [],
            'operating_license' => '',
            'verified_achievements' => [],
            'partners_certifications' => [],
            'reference_sources' => [],
            'content_reviewer' => '',
        ];
    }

    public function getBrandOrientationAttribute($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        } elseif (is_array($value) && ! empty($value)) {
            return $value;
        }

        $envelope = $this->getEmbeddedProfileEnvelope();
        if (! empty($envelope['brand_orientation']) && is_array($envelope['brand_orientation'])) {
            return $envelope['brand_orientation'];
        }

        return [
            'writing_style' => $this->attributes['tone_of_voice'] ?? 'professional',
            'brand_voice' => $this->attributes['tone_of_voice'] ?? 'professional',
            'pronouns_addressing' => 'Doanh nghiệp / Khách hàng (Bạn)',
            'brand_positioning' => $this->attributes['tagline'] ?? '',
            'priority_cta' => '',
        ];
    }

    public function getIndustryProfileAttribute($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && ! empty($decoded)) {
                return $decoded;
            }
        } elseif (is_array($value) && ! empty($value)) {
            return $value;
        }

        $envelope = $this->getEmbeddedProfileEnvelope();
        if (! empty($envelope['industry_profile']) && is_array($envelope['industry_profile'])) {
            return $envelope['industry_profile'];
        }

        $industryKey = $this->getIndustryKeyAttribute($this->attributes['industry_key'] ?? null);

        return app(IndustryProfileRegistry::class)->getProfile($industryKey);
    }
}
