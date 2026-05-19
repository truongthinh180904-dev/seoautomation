<?php

namespace App\Models;

use App\Enums\MediaAssetStatus;
use App\Enums\MediaSourceType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'article_id',
        'source_type',
        'source_url',
        'local_path',
        'wordpress_media_id',
        'wordpress_media_url',
        'alt_text',
        'caption',
        'description',
        'credit',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'source_type' => MediaSourceType::class,
        'status' => MediaAssetStatus::class,
        'metadata' => 'array',
        'wordpress_media_id' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
