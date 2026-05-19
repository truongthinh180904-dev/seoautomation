<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'language' => ['nullable', 'string', 'max:10'],
            'search_volume' => ['nullable', 'integer', 'min:0'],
            'difficulty' => ['nullable', 'integer', 'min:0', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wordpress_site_id' => ['nullable', 'integer', 'exists:wordpress_sites,id'],
            'scheduled_at' => ['nullable', 'date'],
            'pillar_topic' => ['nullable', 'string', 'max:255'],
            'content_cluster' => ['nullable', 'string', 'max:255'],
            'funnel_stage' => ['nullable', 'string', 'max:100'],
            'target_word_count' => ['nullable', 'integer', 'min:300', 'max:10000'],
            'target_url' => ['nullable', 'url', 'max:2000'],
            'canonical_url' => ['nullable', 'url', 'max:2000'],
            'brief_notes' => ['nullable', 'string'],
            'must_include_points' => ['nullable', 'array'],
            'avoid_topics' => ['nullable', 'array'],
            'reference_urls' => ['nullable', 'array'],
            'competitor_urls_override' => ['nullable', 'array'],
        ];
    }
}
