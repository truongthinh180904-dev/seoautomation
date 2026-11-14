<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role !== UserRole::VIEWER;
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
            'keyword' => ['sometimes', 'string', 'max:255'],
            'campaign_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('campaigns', 'id')->where($tenantOrWorkspaceCondition),
            ],
            'language' => ['sometimes', 'string', 'max:10'],
            'search_volume' => ['sometimes', 'integer', 'min:0'],
            'difficulty' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'wordpress_site_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('wordpress_sites', 'id')->where($tenantOrWorkspaceCondition),
            ],
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
            'meta' => ['sometimes', 'nullable', 'array'],
            'meta.featured_image_url' => ['sometimes', 'nullable', 'url', 'max:2000'],
            'meta.image_urls' => ['sometimes', 'nullable'],
            'meta.image_generation_prompt' => ['sometimes', 'nullable', 'string'],
            'meta.image_search_query' => ['sometimes', 'nullable', 'string'],
            'meta.image_source' => ['sometimes', 'nullable', 'in:stock,ai,hybrid'],
            'meta.internal_links' => ['sometimes', 'nullable'],
            'meta.inline_image_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
        ];
    }
}
