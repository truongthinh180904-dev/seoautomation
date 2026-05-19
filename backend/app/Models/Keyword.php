<?php

namespace App\Models;

use App\Enums\KeywordStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Keyword extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'campaign_id',
        'keyword',
        'language',
        'search_volume',
        'difficulty',
        'cpc',
        'search_intent',
        'priority',
        'status',
        'scheduled_at',
        'processed_at',
        'wordpress_site_id',
        'pillar_topic',
        'content_cluster',
        'funnel_stage',
        'target_word_count',
        'target_url',
        'canonical_url',
        'brief_notes',
        'must_include_points',
        'avoid_topics',
        'reference_urls',
        'competitor_urls_override',
        'raw_import_row',
        'template_version',
        'batch_id',
        'meta',
    ];

    protected $casts = [
        'status' => KeywordStatus::class,
        'search_intent' => 'string',
        'meta' => 'array',
        'must_include_points' => 'array',
        'avoid_topics' => 'array',
        'reference_urls' => 'array',
        'competitor_urls_override' => 'array',
        'raw_import_row' => 'array',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
        'priority' => 'integer',
        'target_word_count' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function serpResults(): HasMany
    {
        return $this->hasMany(SerpResult::class);
    }

    public function article(): HasOne
    {
        return $this->hasOne(Article::class);
    }
}
