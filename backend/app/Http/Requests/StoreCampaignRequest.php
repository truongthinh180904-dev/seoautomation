<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string'],
            'language' => ['sometimes', 'string', 'max:10'],
            'brand_voice' => ['nullable', 'string', 'max:255'],
            'target_audience' => ['nullable', 'string', 'max:255'],
            'content_goal' => ['nullable', 'string', 'max:255'],
            'default_word_count' => ['sometimes', 'integer', 'min:300', 'max:10000'],
            'default_category_ids' => ['nullable', 'array'],
            'default_category_ids.*' => ['integer'],
            'default_tag_names' => ['nullable', 'array'],
            'default_tag_names.*' => ['string', 'max:100'],
            'approval_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(CampaignStatus::class)],
            'settings' => ['nullable', 'array'],
        ];
    }
}
