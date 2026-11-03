<?php

namespace App\Http\Requests\Workspace;

use App\Enums\WebsiteStatus;
use App\Services\Security\UrlSafetyValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => [
                'sometimes',
                'url',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && $value !== '' && ! UrlSafetyValidator::isSafeUrl($value)) {
                        $fail("Trường {$attribute} không an toàn hoặc trỏ tới địa chỉ nội bộ bị cấm.");
                    }
                },
            ],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_language' => ['sometimes', 'string', 'max:10'],
            'niche' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(WebsiteStatus::class)],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
