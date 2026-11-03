<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status?->value ?? (string) $this->status,
            'status_label' => $this->status?->label() ?? (string) $this->status,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'last_credit_granted_at' => $this->last_credit_granted_at?->toIso8601String(),
            'next_credit_grant_at' => $this->next_credit_grant_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'plan' => $this->whenLoaded('plan', function () {
                return new PlanResource($this->plan);
            }),
        ];
    }
}
