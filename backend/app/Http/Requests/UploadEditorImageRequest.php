<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadEditorImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Vui lòng chọn một hình ảnh.',
            'image.image' => 'Tệp tải lên phải là hình ảnh.',
            'image.mimes' => 'Chỉ hỗ trợ ảnh JPG, PNG, WEBP hoặc GIF.',
            'image.max' => 'Dung lượng ảnh tối đa 8MB.',
        ];
    }
}
