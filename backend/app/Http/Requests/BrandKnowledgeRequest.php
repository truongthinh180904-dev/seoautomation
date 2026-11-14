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
            'industry_key' => 'nullable|string|max:64',
            'business_model' => 'nullable|string|max:32',
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

            // 3.1 & 3.2: Business Profile (Thông tin cơ bản & Mô tả hoạt động kinh doanh)
            'business_profile' => 'nullable|array',
            'business_profile.company_name' => 'nullable|string|max:255',
            'business_profile.brand_name' => 'nullable|string|max:255',
            'business_profile.website_url' => 'nullable|string|max:255',
            'business_profile.primary_industry' => 'nullable|string|max:150',
            'business_profile.secondary_industry' => 'nullable|string|max:150',
            'business_profile.country_market' => 'nullable|string|max:150',
            'business_profile.service_area' => 'nullable|string|max:500',
            'business_profile.content_language' => 'nullable|string|max:20',
            'business_profile.business_model' => 'nullable|string|max:32',
            'business_profile.company_intro' => 'nullable|string|max:2000',
            'business_profile.company_history' => 'nullable|string|max:2000',
            'business_profile.customer_locations' => 'nullable|string|max:500',
            'business_profile.problems_solved' => 'nullable|string|max:2000',
            'business_profile.competitive_differentiation' => 'nullable|string|max:2000',
            'business_profile.sales_policy' => 'nullable|string|max:1500',
            'business_profile.warranty_policy' => 'nullable|string|max:1500',
            'business_profile.contact_info' => 'nullable|string|max:1000',

            // 3.3: E-E-A-T & Verified Credentials (Thông tin chuyên môn và độ tin cậy)
            'eeat_credentials' => 'nullable|array',
            'eeat_credentials.expert_team' => 'nullable|array',
            'eeat_credentials.expert_team.*' => 'string|max:300',
            'eeat_credentials.verified_qualifications' => 'nullable|array',
            'eeat_credentials.verified_qualifications.*' => 'string|max:300',
            'eeat_credentials.operating_license' => 'nullable|string|max:500',
            'eeat_credentials.verified_achievements' => 'nullable|array',
            'eeat_credentials.verified_achievements.*' => 'string|max:300',
            'eeat_credentials.partners_certifications' => 'nullable|array',
            'eeat_credentials.partners_certifications.*' => 'string|max:300',
            'eeat_credentials.reference_sources' => 'nullable|array',
            'eeat_credentials.reference_sources.*' => 'string|max:500',
            'eeat_credentials.content_reviewer' => 'nullable|string|max:255',

            // 3.4: Brand Orientation (Định hướng thương hiệu)
            'brand_orientation' => 'nullable|array',
            'brand_orientation.writing_style' => 'nullable|string|max:150',
            'brand_orientation.brand_voice' => 'nullable|string|max:150',
            'brand_orientation.pronouns_addressing' => 'nullable|string|max:255',
            'brand_orientation.brand_positioning' => 'nullable|string|max:1000',
            'brand_orientation.priority_cta' => 'nullable|string|max:500',

            // 3.5: Custom Industry Profile overrides (optional)
            'industry_profile' => 'nullable|array',
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
