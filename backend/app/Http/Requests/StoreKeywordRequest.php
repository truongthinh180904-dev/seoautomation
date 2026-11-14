<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKeywordRequest extends FormRequest
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
            'keyword' => ['required', 'string', 'max:255'],
            'campaign_id' => [
                'nullable',
                'integer',
                Rule::exists('campaigns', 'id')->where($tenantOrWorkspaceCondition),
            ],
            'language' => ['nullable', 'string', 'max:10'],
            'search_volume' => ['nullable', 'integer', 'min:0'],
            'difficulty' => ['nullable', 'integer', 'min:0', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'wordpress_site_id' => [
                'nullable',
                'integer',
                Rule::exists('wordpress_sites', 'id')->where($tenantOrWorkspaceCondition),
            ],
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
