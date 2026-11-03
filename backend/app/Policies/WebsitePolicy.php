<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;

class WebsitePolicy
{
    public function view(User $user, Website $website): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $website->workspace->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $workspace->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', [WorkspaceMemberRole::OWNER->value, WorkspaceMemberRole::ADMIN->value, WorkspaceMemberRole::EDITOR->value])
            ->exists();
    }

    public function update(User $user, Website $website): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $website->workspace->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', [WorkspaceMemberRole::OWNER->value, WorkspaceMemberRole::ADMIN->value, WorkspaceMemberRole::EDITOR->value])
            ->exists();
    }

    public function delete(User $user, Website $website): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $website->workspace->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', [WorkspaceMemberRole::OWNER->value, WorkspaceMemberRole::ADMIN->value])
            ->exists();
    }
}
