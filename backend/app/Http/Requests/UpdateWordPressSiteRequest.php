<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Services\Security\UrlSafetyValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWordPressSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role !== UserRole::VIEWER;
    }

    public function rules(): array
    {
        $urlSafetyRule = function ($attribute, $value, $fail) {
            if ($value && ! UrlSafetyValidator::isSafeUrl($value)) {
                $fail("Trường {$attribute} không an toàn hoặc trỏ tới địa chỉ nội bộ bị cấm.");
            }
        };

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:500', $urlSafetyRule],
            'api_url' => ['sometimes', 'url', 'max:500', $urlSafetyRule],
            'username' => ['sometimes', 'string', 'max:255'],
            'app_password' => ['sometimes', 'string', 'max:500'],
            'default_author_id' => ['nullable', 'integer', 'min:1'],
            'default_category_id' => ['nullable', 'integer', 'min:1'],
            'default_status' => ['sometimes', Rule::in(['draft', 'publish'])],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('url') && ! $this->filled('api_url')) {
            $this->merge([
                'api_url' => rtrim((string) $this->input('url'), '/').'/wp-json',
            ]);
        }
    }
}
