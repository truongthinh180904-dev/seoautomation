<?php

namespace App\Http\Requests\Chatbot;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Xác thực quyền gửi yêu cầu.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Quy tắc kiểm tra hợp lệ dữ liệu gửi tin nhắn.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['nullable', 'string', 'in:user,assistant,system,tool'],
            'content' => ['required', 'string', 'max:50000'],
            'images' => ['nullable', 'array'],
            'images.*.id' => ['nullable', 'string'],
            'images.*.previewUrl' => ['nullable', 'string'],
            'images.*.name' => ['nullable', 'string'],
            'images.*.size' => ['nullable', 'numeric'],
            'images.*.mimeType' => ['nullable', 'string'],
            'tool_calls' => ['nullable', 'array'],
            'tool_results' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'action_type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:completed,pending_action,running,failed'],
        ];
    }

    /**
     * Thông báo lỗi tùy chỉnh.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Nội dung tin nhắn không được để trống.',
            'role.in' => 'Vai trò gửi tin nhắn không hợp lệ.',
            'status.in' => 'Trạng thái tin nhắn không hợp lệ.',
        ];
    }
}
