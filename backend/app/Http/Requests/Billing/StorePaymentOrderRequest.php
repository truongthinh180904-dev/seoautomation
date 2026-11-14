<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Vui lòng chọn gói dịch vụ cần thanh toán.',
            'plan_id.exists' => 'Gói dịch vụ được chọn không hợp lệ hoặc đã ngừng hoạt động.',
            'customer_note.max' => 'Ghi chú đơn hàng không được vượt quá 1000 ký tự.',
        ];
    }
}
