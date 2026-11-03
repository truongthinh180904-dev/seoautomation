<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng cung cấp email tài khoản.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
        ];
    }
}
