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
                'required',
                'integer',
                Rule::exists('keywords', 'id')->where($tenantOrWorkspaceCondition),
            ],
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
        ];
    }
}
