<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'legacy_tenant_id' => $this->legacy_tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status?->value ?? (string) $this->status,
            'role' => $this->whenPivotLoaded('workspace_members', function () {
                return $this->pivot->role?->value ?? (string) $this->pivot->role;
            }),
            'available_credits' => $this->whenLoaded('creditAccount', function () {
                return $this->creditAccount?->available_credits ?? 0;
            }),
            'websites_count' => $this->whenCounted('websites'),
            'created_at' => $this->created_at,
        ];
    }
}
