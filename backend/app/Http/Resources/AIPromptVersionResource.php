<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIPromptVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'tenant_id'            => $this->tenant_id,
            'agent_type'           => $this->agent_type,
            'version'              => $this->version,
            'name'                 => $this->name,
            'system_prompt'        => $this->system_prompt,
            'user_prompt_template' => $this->user_prompt_template,
            'variables'            => $this->variables,
            'is_active'            => $this->is_active,
            'is_default'           => $this->is_default,
            'performance_score'    => $this->performance_score,
            'created_by'           => $this->created_by,
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
