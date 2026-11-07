<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ga4PagePerformanceDaily extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ga4_page_performance_daily';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'website_id',
        'article_id',
        'date',
        'landing_page',
        'page_hash',
        'sessions',
        'active_users',
        'engagement_rate',
        'conversions',
        'avg_session_duration',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'sessions' => 'integer',
        'active_users' => 'integer',
        'engagement_rate' => 'float',
        'conversions' => 'integer',
        'avg_session_duration' => 'float',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->page_hash) && ! empty($model->landing_page)) {
                $model->page_hash = static::generatePageHash($model->landing_page);
            }
        });
    }

    /**
     * Generate MD5 hash for normalized landing page.
     */
    public static function generatePageHash(string $landingPage): string
    {
        return md5(trim($landingPage));
    }

    /**
     * Alias for generatePageHash.
     */
    public static function generateHash(string $landingPage): string
    {
        return static::generatePageHash($landingPage);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
