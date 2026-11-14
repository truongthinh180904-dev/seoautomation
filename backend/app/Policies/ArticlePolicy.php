<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function view(User $user, Article $article): bool
    {
        if ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        if ($user->tenant_id && $article->tenant_id === $user->tenant_id) {
            return true;
        }

        if ($user->default_workspace_id && $article->workspace_id === $user->default_workspace_id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role !== UserRole::VIEWER;
    }

    public function update(User $user, Article $article): bool
    {
        if (! $this->view($user, $article)) {
            return false;
        }

        return $user->role !== UserRole::VIEWER;
    }

    public function delete(User $user, Article $article): bool
    {
        if (! $this->view($user, $article)) {
            return false;
        }

        return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true) || $user->is_platform_admin;
    }

    public function approve(User $user, Article $article): bool
    {
        if (! $this->view($user, $article)) {
            return false;
        }

        return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::EDITOR], true);
    }

    public function publish(User $user, Article $article): bool
    {
        if (! $this->view($user, $article)) {
            return false;
        }

        return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true) || $user->is_platform_admin;
    }
}
