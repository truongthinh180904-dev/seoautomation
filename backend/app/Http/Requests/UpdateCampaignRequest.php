<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wordpress_site_id' => ['sometimes', 'nullable', 'integer', 'exists:wordpress_sites,id'],
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'language' => ['sometimes', 'string', 'max:10'],
            'brand_voice' => ['sometimes', 'nullable', 'string', 'max:255'],
            'target_audience' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content_goal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_word_count' => ['sometimes', 'integer', 'min:300', 'max:10000'],
            'default_category_ids' => ['sometimes', 'nullable', 'array'],
            'default_category_ids.*' => ['integer'],
            'default_tag_names' => ['sometimes', 'nullable', 'array'],
            'default_tag_names.*' => ['string', 'max:100'],
            'approval_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(CampaignStatus::class)],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
