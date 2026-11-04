<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KeywordResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()?->getActionMethod() ?? '';

        if ($action === 'cluster' || $this->is('*/keywords/cluster')) {
            return [
                'keywords' => ['required', 'array', 'min:2', 'max:200'],
                'keywords.*' => ['required'],
                'language' => ['nullable', 'string', 'max:10'],
            ];
        }

        if ($action === 'saveToCampaign' || $this->is('*/keywords/research/save')) {
            return [
                'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
                'website_id' => ['nullable', 'integer', 'exists:websites,id'],
                'keywords' => ['required', 'array', 'min:1'],
                'keywords.*.keyword' => ['required', 'string', 'max:500'],
                'keywords.*.search_intent' => ['nullable', 'string', Rule::in(['informational', 'navigational', 'commercial', 'transactional'])],
                'keywords.*.search_volume' => ['nullable', 'integer', 'min:0'],
                'keywords.*.difficulty' => ['nullable', 'integer', 'min:0', 'max:100'],
                'keywords.*.cpc' => ['nullable', 'numeric', 'min:0'],
                'keywords.*.pillar_topic' => ['nullable', 'string', 'max:255'],
                'keywords.*.content_cluster' => ['nullable', 'string', 'max:255'],
                'keywords.*.lsi_keywords' => ['nullable', 'array'],
            ];
        }

        // Default: research endpoint
        return [
            'query' => ['required_without:keyword', 'nullable', 'string', 'min:2', 'max:255'],
            'keyword' => ['required_without:query', 'nullable', 'string', 'min:2', 'max:255'],
            'language' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:10'],
            'limit' => ['nullable', 'integer', 'min:5', 'max:100'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.required_without' => 'Vui lòng cung cấp từ khóa hạt giống (query hoặc keyword) để nghiên cứu.',
            'keyword.required_without' => 'Vui lòng cung cấp từ khóa hạt giống (query hoặc keyword) để nghiên cứu.',
            'query.min' => 'Từ khóa tìm kiếm phải có ít nhất 2 ký tự.',
            'keyword.min' => 'Từ khóa tìm kiếm phải có ít nhất 2 ký tự.',
            'keywords.required' => 'Danh sách từ khóa không được để trống.',
            'keywords.min' => 'Cần ít nhất :min từ khóa để thực hiện tác vụ.',
            'keywords.*.keyword.required' => 'Mỗi mục phải có tên từ khóa hợp lệ.',
        ];
    }
}
