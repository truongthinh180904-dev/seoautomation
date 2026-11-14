<?php

namespace App\Http\Requests\Admin;

use App\Enums\WorkspaceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateWorkspaceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->is_platform_admin || $this->user()->role?->value === 'super_admin' || $this->user()->role === 'super_admin');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(WorkspaceStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng chọn trạng thái không gian làm việc.',
        ];
    }
}
