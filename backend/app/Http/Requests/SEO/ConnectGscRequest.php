<?php

namespace App\Http\Requests\SEO;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConnectGscRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_url' => ['required', 'string', 'max:500'],
            'google_account_id' => ['nullable', 'integer', 'exists:google_accounts,id'],
            'property_type' => ['nullable', 'string', 'in:url_prefix,domain'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_url.required' => 'Vui lòng cung cấp URL hoặc Domain Property của Search Console.',
            'site_url.max' => 'URL hoặc Domain Property không được vượt quá 500 ký tự.',
            'google_account_id.exists' => 'Tài khoản Google được chọn không tồn tại.',
            'property_type.in' => 'Loại property phải là url_prefix hoặc domain.',
        ];
    }
}
