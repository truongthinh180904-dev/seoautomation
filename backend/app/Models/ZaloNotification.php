<?php

namespace App\Models;

use App\Enums\ZaloMessageType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZaloNotification extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'article_id',
        'user_id',
        'zalo_user_id',
        'message_type',
        'message_text',
        'zalo_message_id',
        'status',
        'sent_at',
        'error_message',
    ];

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $casts = [
        'message_type' => ZaloMessageType::class,
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
