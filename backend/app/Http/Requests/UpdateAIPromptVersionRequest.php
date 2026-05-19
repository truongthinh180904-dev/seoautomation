<?php

namespace App\Http\Requests;

use App\Enums\AgentType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAIPromptVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true);
    }

    public function rules(): array
    {
        return [
            'agent_type' => ['sometimes', Rule::enum(AgentType::class)],
            'version' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'nullable', 'string'],
            'user_prompt_template' => ['sometimes', 'string'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
        ];
    }
}
