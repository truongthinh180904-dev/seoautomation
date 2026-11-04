<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalLinkSuggestion extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $table = 'internal_link_suggestions';

    protected $fillable = [
        'workspace_id',
        'website_id',
        'source_article_id',
        'target_article_id',
        'suggested_anchor_text',
        'context_sentence',
        'relevance_score',
        'status',
    ];

    protected $casts = [
        'relevance_score' => 'float',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function sourceArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'source_article_id');
    }

    public function targetArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'target_article_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', 'applied');
    }

    public function scopeForWebsite(Builder $query, int $websiteId): Builder
    {
        return $query->where('website_id', $websiteId);
    }

    public function scopeForArticle(Builder $query, int $articleId): Builder
    {
        return $query->where(function (Builder $q) use ($articleId) {
            $q->where('source_article_id', $articleId)
                ->orWhere('target_article_id', $articleId);
        });
    }
}
