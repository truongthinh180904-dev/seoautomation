<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:10'],
            'search_volume' => ['nullable', 'integer', 'min:0'],
            'difficulty' => ['nullable', 'integer', 'min:0', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wordpress_site_id' => ['nullable', 'integer', 'exists:wordpress_sites,id'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
