<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WordPressSite extends Model
{
    use BelongsToTenant;

    protected $table = 'wordpress_sites';

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'website_id',
        'name',
        'url',
        'api_url',
        'username',
        'app_password',
        'default_author_id',
        'default_category_id',
        'default_status',
        'is_active',
        'last_connected_at',
        'connection_status',
        'settings',
    ];

    protected $hidden = [
        'app_password',
    ];

    protected $casts = [
        'app_password' => 'encrypted',
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_connected_at' => 'datetime',
        'connection_status' => 'string',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function publishingLogs(): HasMany
    {
        return $this->hasMany(PublishingLog::class);
    }
}
