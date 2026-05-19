<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'string', 'max:255'],
            'campaign_id' => ['sometimes', 'nullable', 'integer', 'exists:campaigns,id'],
            'language' => ['sometimes', 'string', 'max:10'],
            'search_volume' => ['sometimes', 'integer', 'min:0'],
            'difficulty' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'wordpress_site_id' => ['sometimes', 'nullable', 'integer', 'exists:wordpress_sites,id'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'pillar_topic' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content_cluster' => ['sometimes', 'nullable', 'string', 'max:255'],
            'funnel_stage' => ['sometimes', 'nullable', 'string', 'max:100'],
            'target_word_count' => ['sometimes', 'nullable', 'integer', 'min:300', 'max:10000'],
            'target_url' => ['sometimes', 'nullable', 'url', 'max:2000'],
            'canonical_url' => ['sometimes', 'nullable', 'url', 'max:2000'],
            'brief_notes' => ['sometimes', 'nullable', 'string'],
            'must_include_points' => ['sometimes', 'nullable', 'array'],
            'avoid_topics' => ['sometimes', 'nullable', 'array'],
            'reference_urls' => ['sometimes', 'nullable', 'array'],
            'competitor_urls_override' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
