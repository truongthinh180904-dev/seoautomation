<?php

namespace App\Http\Requests\SEO;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrackedKeywordRequest extends FormRequest
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
            'website_id' => 'required|integer|exists:websites,id',
            'keyword' => 'required|string|max:255',
            'target_url' => 'nullable|string|max:1024',
            'country' => 'nullable|string|max:3',
            'device' => 'nullable|string|in:desktop,mobile',
            'tracking_frequency' => 'nullable|string|in:daily,weekly',
            'keyword_id' => 'nullable|integer|exists:keywords,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website_id.required' => 'Vui lòng chọn website cần theo dõi.',
            'website_id.exists' => 'Website không tồn tại trong hệ thống.',
            'keyword.required' => 'Vui lòng nhập từ khóa cần theo dõi thứ hạng.',
            'keyword.max' => 'Từ khóa không được vượt quá 255 ký tự.',
            'device.in' => 'Thiết bị theo dõi phải là desktop hoặc mobile.',
            'tracking_frequency.in' => 'Tần suất theo dõi phải là daily hoặc weekly.',
        ];
    }
}
