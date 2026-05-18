<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'type' => $this->type,
            'cron_expression' => $this->cron_expression,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'is_recurring' => $this->is_recurring,
            'status' => $this->status,
            'config' => $this->config,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'run_count' => $this->run_count,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                if (!$this->creator) {
                    return null;
                }

                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
