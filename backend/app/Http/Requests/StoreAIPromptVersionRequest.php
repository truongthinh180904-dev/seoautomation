<?php

namespace App\Http\Requests;

use App\Enums\AgentType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAIPromptVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true);
    }

    public function rules(): array
    {
        return [
            'agent_type' => ['required', Rule::enum(AgentType::class)],
            'version' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string'],
            'user_prompt_template' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ];
    }
}
