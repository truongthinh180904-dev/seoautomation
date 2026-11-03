<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_order_id' => $this->payment_order_id,
            'workspace_id' => $this->workspace_id,
            'transaction_code' => $this->transaction_code,
            'amount_vnd' => (int) $this->amount_vnd,
            'amount_formatted' => number_format((int) $this->amount_vnd).'đ',
            'direction' => $this->direction?->value ?? (string) $this->direction,
            'status' => $this->status?->value ?? (string) $this->status,
            'status_label' => $this->status?->label() ?? (string) $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
