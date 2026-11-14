<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
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

        return [
            'wordpress_site_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('wordpress_sites', 'id')->where(function ($query) use ($workspaceId, $tenantId) {
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
                }),
            ],
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
