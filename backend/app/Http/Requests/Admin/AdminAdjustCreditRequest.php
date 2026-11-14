<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminAdjustCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->is_platform_admin || $this->user()->role?->value === 'super_admin' || $this->user()->role === 'super_admin');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Vui lòng nhập số credit cần điều chỉnh.',
            'amount.not_in' => 'Số credit điều chỉnh không được bằng 0.',
            'reason.required' => 'Vui lòng cung cấp lý do điều chỉnh credit.',
        ];
    }
}
