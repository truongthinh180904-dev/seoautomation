<?php

namespace App\Http\Requests\Workspace;

use App\Enums\WorkspaceMemberRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddWorkspaceMemberRequest extends FormRequest
{
    /**
     * Only active owners/admins of the target workspace (or platform admins) may add members.
     * Authorization runs before validation, so unauthorized callers always receive 403.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $workspace = Workspace::find((int) $this->route('id'));

        if ($user === null || $workspace === null) {
            return false;
        }

        return $user->can('manageMembers', $workspace);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => [
                'sometimes',
                Rule::enum(WorkspaceMemberRole::class),
                Rule::notIn([WorkspaceMemberRole::OWNER->value]),
            ],
        ];
    }
}
