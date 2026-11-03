<?php

namespace App\Http\Requests\Billing;

use App\Models\PaymentOrder;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $orderId = $this->route('id');
        if (! $orderId) {
            return true;
        }

        $order = PaymentOrder::find($orderId);
        if (! $order) {
            return true;
        }

        return (bool) $user->can('submitProof', $order);
    }

    public function rules(): array
    {
        return [
            'proof_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120', 'required_without:proof_image_path'],
            'proof_image_path' => [
                'nullable',
                'string',
                'max:2000',
                'required_without:proof_image',
                'not_regex:/\.\./',
                'regex:/^(https?:\/\/.+|payment_proofs\/[a-zA-Z0-9_\-\.\/]+)$/',
            ],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'proof_image.required_without' => 'Vui lòng tải lên ảnh chụp minh chứng chuyển khoản thành công.',
            'proof_image.image' => 'Tệp minh chứng phải là định dạng hình ảnh hợp lệ.',
            'proof_image.max' => 'Dung lượng ảnh minh chứng không được vượt quá 5MB.',
            'proof_image_path.not_regex' => 'Đường dẫn tệp minh chứng không được chứa ký tự chuyển thư mục.',
            'proof_image_path.regex' => 'Đường dẫn tệp minh chứng phải là URL hợp lệ hoặc thuộc thư mục payment_proofs.',
            'customer_note.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
        ];
    }
}
