<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportKeywordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role->value, [
            'super_admin',
            'admin',
            'editor',
        ], true);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'campaign_id' => ['sometimes', 'nullable', 'integer', 'exists:campaigns,id'],
        ];
    }
}
