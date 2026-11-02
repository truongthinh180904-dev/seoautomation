<?php

namespace App\Models;

use App\Enums\PaymentOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'plan_id',
        'reference_code',
        'amount_vnd',
        'currency',
        'payment_method',
        'status',
        'bank_info_snapshot',
        'proof_image_path',
        'customer_note',
        'proof_submitted_at',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'rejection_reason',
        'idempotency_key',
        'expires_at',
    ];

    protected $casts = [
        'status' => PaymentOrderStatus::class,
        'amount_vnd' => 'integer',
        'bank_info_snapshot' => 'array',
        'proof_submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function creditGrants(): HasMany
    {
        return $this->hasMany(CreditGrant::class);
    }
}
