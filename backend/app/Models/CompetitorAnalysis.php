<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorAnalysis extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'serp_result_id',
        'keyword_id',
        'tenant_id',
        'url',
        'word_count',
        'heading_structure',
        'semantic_entities',
        'semantic_keywords',
        'search_intent',
        'faqs',
        'article_structure',
        'topical_relevance',
        'raw_html_hash',
        'analysis_completed',
        'analyzed_at',
    ];

    protected $casts = [
        'heading_structure' => 'array',
        'semantic_entities' => 'array',
        'semantic_keywords' => 'array',
        'faqs' => 'array',
        'article_structure' => 'array',
        'analysis_completed' => 'boolean',
        'analyzed_at' => 'datetime',
        'topical_relevance' => 'float',
    ];

    public function serpResult(): BelongsTo
    {
        return $this->belongsTo(SerpResult::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
