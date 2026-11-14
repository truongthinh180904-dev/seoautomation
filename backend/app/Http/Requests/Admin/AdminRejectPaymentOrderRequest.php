<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminRejectPaymentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->is_platform_admin || $this->user()->role?->value === 'super_admin' || $this->user()->role === 'super_admin');
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:3', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Vui lòng cung cấp lý do từ chối đơn thanh toán.',
            'rejection_reason.min' => 'Lý do từ chối phải có ít nhất 3 ký tự.',
        ];
    }
}
