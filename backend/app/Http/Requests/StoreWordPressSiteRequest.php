<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWordPressSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'api_url' => ['required', 'url', 'max:500'],
            'username' => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'max:500'],
            'default_author_id' => ['nullable', 'integer', 'min:1'],
            'default_category_id' => ['nullable', 'integer', 'min:1'],
            'default_status' => ['sometimes', Rule::in(['draft', 'publish'])],
            'is_active' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('api_url') && $this->filled('url')) {
            $this->merge([
                'api_url' => rtrim((string) $this->input('url'), '/') . '/wp-json',
            ]);
        }
    }
}
