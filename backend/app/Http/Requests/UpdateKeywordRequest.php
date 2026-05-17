<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:255'],
            'language' => ['sometimes', 'string', 'max:10'],
            'search_volume' => ['sometimes', 'integer', 'min:0'],
            'difficulty' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'wordpress_site_id' => ['sometimes', 'nullable', 'integer', 'exists:wordpress_sites,id'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
