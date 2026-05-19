<?php

namespace App\Models;

use App\Enums\CampaignImportStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignImport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'template_version',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'imported_rows',
        'skipped_rows',
        'errors',
        'warnings',
        'estimated_cost',
        'status',
    ];

    protected $casts = [
        'status' => CampaignImportStatus::class,
        'errors' => 'array',
        'warnings' => 'array',
        'estimated_cost' => 'array',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'invalid_rows' => 'integer',
        'imported_rows' => 'integer',
        'skipped_rows' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
