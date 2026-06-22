<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Traits\BelongsToTenant;
use App\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    use BelongsToTenant, HasVersions;

    protected $fillable = [
        'tenant_id',
        'keyword_id',
        'campaign_id',
        'wordpress_site_id',
        'user_id',
        'reviewed_by',
        'published_by',
        'title',
        'slug',
        'content',
        'excerpt',
        'seo_title',
        'seo_description',
        'focus_keyword',
        'word_count',
        'seo_score',
        'readability_score',
        'outline',
        'faqs',
        'internal_links',
        'featured_image_url',
        'ai_provider',
        'ai_model',
        'ai_tokens_used',
        'ai_cost_usd',
        'status',
        'review_token',
        'review_notes',
        'rejection_reason',
        'scheduled_publish_at',
        'published_at',
        'wordpress_post_id',
        'wordpress_post_url',
        'wp_post_type',
        'wp_status',
        'wp_category_ids',
        'wp_tag_ids',
        'wp_tag_names',
        'wp_author_id',
        'wp_slug',
        'canonical_url',
        'primary_cta',
        'secondary_cta',
        'media_plan',
        'image_assets',
        'quality_report',
        'pipeline_status',
        'approval_required',
        'approved_at',
        'duplicate_check_hash',
    ];

    protected $casts = [
        'status' => ArticleStatus::class,
        'outline' => 'array',
        'faqs' => 'array',
        'internal_links' => 'array',
        'wp_category_ids' => 'array',
        'wp_tag_ids' => 'array',
        'wp_tag_names' => 'array',
        'media_plan' => 'array',
        'image_assets' => 'array',
        'quality_report' => 'array',
        'pipeline_status' => 'array',
        'scheduled_publish_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'approval_required' => 'boolean',
        'word_count' => 'integer',
        'seo_score' => 'integer',
        'readability_score' => 'integer',
        'ai_tokens_used' => 'integer',
        'ai_cost_usd' => 'float',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function semanticEntities(): HasMany
    {
        return $this->hasMany(SemanticEntity::class);
    }

    public function internalLinks(): HasMany
    {
        return $this->hasMany(InternalLink::class, 'source_article_id');
    }

    public function publishingLogs(): HasMany
    {
        return $this->hasMany(PublishingLog::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function qualityReports(): HasMany
    {
        return $this->hasMany(QualityReport::class);
    }

    public function generateReviewToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->review_token = $token;
        $this->save();
        return $token;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ArticleStatus::DRAFT, ArticleStatus::REVIEW]);
    }

    public function duplicateCheckHash(string $content): string
    {
        return hash('sha256', strip_tags($content));
    }
}
