<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_order_id',
        'workspace_id',
        'transaction_code',
        'amount_vnd',
        'direction',
        'status',
        'verified_by',
        'paid_at',
        'raw_payload',
    ];

    protected $casts = [
        'status' => PaymentTransactionStatus::class,
        'direction' => PaymentDirection::class,
        'amount_vnd' => 'integer',
        'paid_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
