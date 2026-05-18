<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePromptPerformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true);
    }

    public function rules(): array
    {
        return [
            'seo_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
