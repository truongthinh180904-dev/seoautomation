<?php

namespace App\Http\Requests\SEO;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConnectGa4Request extends FormRequest
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
            'property_id' => ['required', 'string', 'max:100'],
            'property_name' => ['nullable', 'string', 'max:255'],
            'account_id' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'measurement_id' => ['nullable', 'string', 'max:50'],
            'google_account_id' => ['nullable', 'integer', 'exists:google_accounts,id'],
            'time_zone' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['nullable', 'string', 'max:20'],
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
            'property_id.required' => 'Vui lòng cung cấp Google Analytics 4 Property ID.',
            'property_id.max' => 'Property ID không được vượt quá 100 ký tự.',
            'google_account_id.exists' => 'Tài khoản Google được chọn không tồn tại.',
            'time_zone.max' => 'Múi giờ không được vượt quá 100 ký tự.',
            'currency_code.max' => 'Mã tiền tệ không được vượt quá 20 ký tự.',
        ];
    }
}
