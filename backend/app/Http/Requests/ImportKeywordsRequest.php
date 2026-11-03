<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportKeywordsRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'campaign_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('campaigns', 'id')->where(function ($query) use ($workspaceId, $tenantId) {
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
        ];
    }
}
