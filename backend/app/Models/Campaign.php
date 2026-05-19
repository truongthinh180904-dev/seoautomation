<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'wordpress_site_id',
        'created_by',
        'name',
        'slug',
        'description',
        'language',
        'brand_voice',
        'target_audience',
        'content_goal',
        'default_word_count',
        'default_category_ids',
        'default_tag_names',
        'approval_required',
        'status',
        'settings',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => CampaignStatus::class,
        'default_category_ids' => 'array',
        'default_tag_names' => 'array',
        'approval_required' => 'boolean',
        'settings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'default_word_count' => 'integer',
    ];

    public function wordpressSite(): BelongsTo
    {
        return $this->belongsTo(WordPressSite::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(CampaignImport::class);
    }
}
