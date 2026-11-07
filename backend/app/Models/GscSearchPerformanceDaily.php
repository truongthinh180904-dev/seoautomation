<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscSearchPerformanceDaily extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'gsc_search_performance_daily';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'website_id',
        'date',
        'query',
        'page_url',
        'page_hash',
        'query_hash',
        'country',
        'device',
        'clicks',
        'impressions',
        'ctr',
        'position',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->page_hash) && ! empty($model->page_url)) {
                $model->page_hash = static::generatePageHash($model->page_url);
            }
            if (empty($model->query_hash) && isset($model->query)) {
                $model->query_hash = static::generateQueryHash($model->query);
            }
        });
    }

    /**
     * Generate MD5 hash for URL.
     */
    public static function generatePageHash(string $url): string
    {
        return md5(trim($url));
    }

    /**
     * Alias for generatePageHash.
     */
    public static function generateHash(string $url): string
    {
        return static::generatePageHash($url);
    }

    /**
     * Generate MD5 hash for search query.
     */
    public static function generateQueryHash(string $query): string
    {
        return md5(trim(mb_strtolower($query)));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
