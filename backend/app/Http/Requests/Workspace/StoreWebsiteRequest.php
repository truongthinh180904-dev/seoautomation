<?php

namespace App\Http\Requests\Workspace;

use App\Enums\WebsiteStatus;
use App\Models\Workspace;
use App\Services\Security\UrlSafetyValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('current_workspace');
        if (! $workspace && $this->user()) {
            $workspaceId = (int) ($this->header('X-Workspace-Id') ?: $this->query('workspace_id') ?: $this->user()->default_workspace_id);
            if ($workspaceId) {
                $workspace = Workspace::find($workspaceId);
            }
        }
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $this->user()?->default_workspace_id;
        $tenantId = $workspace instanceof Workspace ? $workspace->legacy_tenant_id : $this->user()?->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'required',
                'url',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && $value !== '' && ! UrlSafetyValidator::isSafeUrl($value)) {
                        $fail("Trường {$attribute} không an toàn hoặc trỏ tới địa chỉ nội bộ bị cấm.");
                    }
                },
            ],
            'domain' => ['nullable', 'string', 'max:255'],
            'wordpress_site_id' => [
                'nullable',
                'integer',
                Rule::exists('wordpress_sites', 'id')->where(function ($query) use ($workspaceId, $tenantId) {
                    $query->where(function ($q) use ($workspaceId, $tenantId) {
                        $q->where('workspace_id', $workspaceId ?? 0);
                        if ($tenantId) {
                            $q->orWhere(function ($legacy) use ($tenantId) {
                                $legacy->whereNull('workspace_id')->where('tenant_id', $tenantId);
                            });
                        }
                    });
                }),
            ],
            'default_language' => ['sometimes', 'string', 'max:10'],
            'niche' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(WebsiteStatus::class)],
            'settings' => ['nullable', 'array'],
        ];
    }
}
