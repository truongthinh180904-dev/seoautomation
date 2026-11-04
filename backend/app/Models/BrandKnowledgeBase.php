<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandKnowledgeBase extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $table = 'brand_knowledge_bases';

    protected $fillable = [
        'workspace_id',
        'website_id',
        'brand_name',
        'tagline',
        'tone_of_voice',
        'target_personas',
        'unique_selling_points',
        'product_catalog',
        'taboo_words',
        'preferred_terms',
        'custom_rules',
        'is_default',
    ];

    protected $casts = [
        'target_personas' => 'array',
        'unique_selling_points' => 'array',
        'product_catalog' => 'array',
        'taboo_words' => 'array',
        'preferred_terms' => 'array',
        'is_default' => 'boolean',
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
}
