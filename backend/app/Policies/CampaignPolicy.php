<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        if ($user->tenant_id && $campaign->tenant_id === $user->tenant_id) {
            return true;
        }

        if ($user->default_workspace_id && $campaign->workspace_id === $user->default_workspace_id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role !== UserRole::VIEWER;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        if (! $this->view($user, $campaign)) {
            return false;
        }

        return $user->role !== UserRole::VIEWER;
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        if (! $this->view($user, $campaign)) {
            return false;
        }

        return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true) || $user->is_platform_admin;
    }
}
