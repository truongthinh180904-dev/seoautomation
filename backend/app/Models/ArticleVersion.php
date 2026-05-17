<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleVersion extends Model
{
    protected $fillable = [
        'article_id',
        'tenant_id',
        'version_number',
        'title',
        'content',
        'seo_title',
        'seo_description',
        'word_count',
        'seo_score',
        'changed_by',
        'change_reason',
        'diff_summary',
    ];

    public $timestamps = false;

    protected $casts = [
        'diff_summary' => 'array',
        'version_number' => 'integer',
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
