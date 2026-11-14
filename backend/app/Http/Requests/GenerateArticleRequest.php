<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::EDITOR], true);
    }

    public function rules(): array
    {
        $user = $this->user();
        $workspace = $this->attributes->get('current_workspace');
        if (! $workspace && $user) {
            $workspaceId = (int) ($this->header('X-Workspace-Id') ?: $this->query('workspace_id') ?: $user->default_workspace_id);
            if ($workspaceId) {
                $workspace = Workspace::find($workspaceId);
            }
        }
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $user?->default_workspace_id;
        $tenantId = $workspace instanceof Workspace ? $workspace->legacy_tenant_id : $user?->tenant_id;

        $tenantOrWorkspaceCondition = function ($query) use ($workspaceId, $tenantId) {
            $query->where(function ($q) use ($workspaceId, $tenantId) {
                if ($workspaceId) {
                    $q->where('workspace_id', $workspaceId);
                }
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
                if (! $workspaceId && ! $tenantId) {
                    $q->whereRaw('1 = 0');
                }
            });
        };

        return [
            'keyword_id' => [
                'nullable',
                'required_without:primary_keyword',
                'integer',
                Rule::exists('keywords', 'id')->where($tenantOrWorkspaceCondition),
            ],
            'primary_keyword' => 'nullable|required_without:keyword_id|string|max:255',
            'website_id' => 'nullable|integer',
            'brand_knowledge_id' => 'nullable|integer',
            'wordpress_site_id' => [
                'nullable',
                'integer',
                Rule::exists('wordpress_sites', 'id')->where($tenantOrWorkspaceCondition),
            ],
            'campaign_id' => [
                'nullable',
                'integer',
                Rule::exists('campaigns', 'id')->where($tenantOrWorkspaceCondition),
            ],

            // PHẦN II: CONTENT GENERATION FORM (Section 4)
            'topic_description' => 'nullable|string|max:2000',
            'content_language' => 'nullable|string|max:20',
            'secondary_keywords' => 'nullable',
            'related_keywords' => 'nullable',
            'local_seo_location' => 'nullable|string|max:255',
            'target_product_service' => 'nullable|string|max:255',
            'target_audience' => 'nullable|string|max:500',
            'article_goal' => 'nullable|string|max:500',
            'desired_length' => 'nullable|string|max:50',
            'writing_style' => 'nullable|string|max:150',
            'desired_cta' => 'nullable|string|max:500',
            'reference_urls' => 'nullable',
            'internal_link_urls' => 'nullable',
            'competitor_urls' => 'nullable',
            'desired_image_count' => 'nullable|integer|min:0|max:10',
            'special_requirements' => 'nullable|string|max:3000',
            'content_type' => 'nullable|string|max:64',
            'search_intent' => 'nullable|string|max:64',
            'require_outline_approval' => 'nullable|boolean',
        ];
    }
}
