<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class GenerateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::EDITOR], true);
    }

    public function rules(): array
    {
        return [
            'keyword_id' => ['required', 'integer', 'exists:keywords,id'],
            'wordpress_site_id' => ['nullable', 'integer', 'exists:wordpress_sites,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ];
    }
}
