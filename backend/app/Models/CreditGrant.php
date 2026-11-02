<?php

namespace App\Models;

use App\Enums\CreditGrantSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_account_id',
        'workspace_id',
        'subscription_id',
        'payment_order_id',
        'source_type',
        'credits_granted',
        'credits_remaining',
        'granted_at',
        'expires_at',
        'expired_processed_at',
        'granted_by',
        'idempotency_key',
        'note',
    ];

    protected $casts = [
        'source_type' => CreditGrantSource::class,
        'credits_granted' => 'integer',
        'credits_remaining' => 'integer',
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'expired_processed_at' => 'datetime',
    ];

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
