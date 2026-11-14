<?php

namespace App\Http\Requests\Chatbot;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    /**
     * Xác thực quyền gửi yêu cầu.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Quy tắc kiểm tra hợp lệ dữ liệu tạo cuộc trò chuyện.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspaceId = $this->header('X-Workspace-Id')
            ?: $this->user()?->default_workspace_id
            ?: $this->user()?->workspaces()->first()?->id;

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'website_id' => [
                'nullable',
                'integer',
                Rule::exists('websites', 'id')->where(function ($query) use ($workspaceId) {
                    if ($workspaceId) {
                        $query->where('workspace_id', (int) $workspaceId);
                    }
                }),
            ],
            'current_mode' => ['nullable', 'string', 'in:general,research,writing,audit,bulk'],
            'context_summary' => ['nullable', 'string'],
            'pinned' => ['nullable', 'boolean'],
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
            'website_id.exists' => 'Website được chọn không tồn tại hoặc không thuộc Không gian làm việc này.',
            'current_mode.in' => 'Chế độ hội thoại không hợp lệ.',
        ];
    }
}
