<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BrandKnowledgeRequest extends FormRequest
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
        $isPost = $this->isMethod('post');

        return [
            'website_id' => $isPost ? 'required|integer' : 'sometimes|required|integer',
            'brand_name' => $isPost ? 'required|string|max:255' : 'sometimes|required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'tone_of_voice' => 'nullable|string|max:100',
            'target_personas' => 'nullable|array',
            'target_personas.*' => 'string|max:255',
            'unique_selling_points' => 'nullable|array',
            'unique_selling_points.*' => 'string|max:255',
            'product_catalog' => 'nullable|array',
            'product_catalog.*.name' => 'nullable|string|max:255',
            'product_catalog.*.description' => 'nullable|string|max:1000',
            'product_catalog.*.url' => 'nullable|string|max:255',
            'taboo_words' => 'nullable|array',
            'taboo_words.*' => 'string|max:100',
            'preferred_terms' => 'nullable|array',
            'preferred_terms.*' => 'string|max:100',
            'custom_rules' => 'nullable|string|max:5000',
            'is_default' => 'nullable|boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website_id.required' => 'Vui lòng chọn Website cho hồ sơ thương hiệu.',
            'website_id.integer' => 'Mã định danh Website không hợp lệ.',
            'brand_name.required' => 'Tên thương hiệu là bắt buộc.',
            'brand_name.max' => 'Tên thương hiệu không được vượt quá 255 ký tự.',
            'tagline.max' => 'Khẩu hiệu/Slogan không được vượt quá 255 ký tự.',
            'tone_of_voice.max' => 'Giọng văn thương hiệu không được vượt quá 100 ký tự.',
            'custom_rules.max' => 'Quy tắc viết văn không được vượt quá 5000 ký tự.',
        ];
    }
}
