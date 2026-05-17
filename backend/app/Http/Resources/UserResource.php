<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'tenant_id' => $this->tenant_id,
            'avatar_url' => $this->avatar_url,
            'zalo_user_id' => $this->zalo_user_id,
            'last_login_at' => $this->last_login_at,
            'is_active' => $this->is_active,
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                    'slug' => $this->tenant->slug,
                    'plan' => $this->tenant->plan,
                ];
            }),
        ];
    }
}
