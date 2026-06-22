<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:500'],
            'content' => ['sometimes', 'nullable', 'string'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'review', 'approved', 'rejected'])],
            'campaign_id' => ['sometimes', 'nullable', 'integer', 'exists:campaigns,id'],
            'wp_post_type' => ['sometimes', 'string', 'max:50'],
            'wp_status' => ['sometimes', 'nullable', Rule::in(['draft', 'publish', 'future', 'private'])],
            'wp_category_ids' => ['sometimes', 'nullable', 'array'],
            'wp_category_ids.*' => ['integer'],
            'wp_tag_ids' => ['sometimes', 'nullable', 'array'],
            'wp_tag_ids.*' => ['integer'],
            'wp_tag_names' => ['sometimes', 'nullable', 'array'],
            'wp_tag_names.*' => ['string', 'max:100'],
            'wp_author_id' => ['sometimes', 'nullable', 'integer'],
            'wp_slug' => ['sometimes', 'nullable', 'string', 'max:500'],
            'canonical_url' => ['sometimes', 'nullable', 'url', 'max:2000'],
            'primary_cta' => ['sometimes', 'nullable', 'string'],
            'secondary_cta' => ['sometimes', 'nullable', 'string'],
            'internal_links' => ['sometimes', 'nullable', 'array'],
            'media_plan' => ['sometimes', 'nullable', 'array'],
            'media_plan.inline_image_count' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'media_plan.featured_image_url' => ['sometimes', 'nullable', 'url', 'max:2000'],
            'media_plan.image_urls' => ['sometimes', 'nullable'],
            'media_plan.internal_links' => ['sometimes', 'nullable'],
            'media_plan.image_generation_prompt' => ['sometimes', 'nullable', 'string'],
            'media_plan.image_search_query' => ['sometimes', 'nullable', 'string'],
            'media_plan.image_source' => ['sometimes', 'nullable', 'in:stock,ai,hybrid'],
            'image_assets' => ['sometimes', 'nullable', 'array'],
            'approval_required' => ['sometimes', 'boolean'],
        ];
    }
}
