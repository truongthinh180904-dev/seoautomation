<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'credit_account_id' => $this->credit_account_id,
            'workspace_id' => $this->workspace_id,
            'credit_grant_id' => $this->credit_grant_id,
            'type' => $this->type?->value ?? (string) $this->type,
            'type_label' => $this->type?->label() ?? (string) $this->type,
            'amount' => (int) $this->amount,
            'balance_before' => (int) $this->balance_before,
            'balance_after' => (int) $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'actor_id' => $this->actor_id,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
