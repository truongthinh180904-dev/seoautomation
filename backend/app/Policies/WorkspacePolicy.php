<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $workspace->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $workspace->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', [WorkspaceMemberRole::OWNER->value, WorkspaceMemberRole::ADMIN->value])
            ->exists();
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $workspace->owner_id === $user->id;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $workspace->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', [WorkspaceMemberRole::OWNER->value, WorkspaceMemberRole::ADMIN->value])
            ->exists();
    }
}
