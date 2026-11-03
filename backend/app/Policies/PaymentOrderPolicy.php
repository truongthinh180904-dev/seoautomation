<?php

namespace App\Policies;

use App\Enums\PaymentOrderStatus;
use App\Enums\UserRole;
use App\Models\PaymentOrder;
use App\Models\User;

class PaymentOrderPolicy
{
    protected function isPlatformAdmin(User $user): bool
    {
        return (bool) ($user->is_platform_admin || $user->role === UserRole::SUPER_ADMIN || $user->role?->value === 'super_admin' || $user->role === 'super_admin');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentOrder $order): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        $currentWorkspaceId = app()->bound('current_workspace_id') ? (int) app('current_workspace_id') : null;
        if ($currentWorkspaceId && (int) $order->workspace_id !== $currentWorkspaceId) {
            return false;
        }

        if ($order->user_id === $user->id) {
            return true;
        }

        return (bool) ($order->workspace?->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists());
    }

    public function submitProof(User $user, PaymentOrder $order): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        $currentWorkspaceId = app()->bound('current_workspace_id') ? (int) app('current_workspace_id') : null;
        if ($currentWorkspaceId && (int) $order->workspace_id !== $currentWorkspaceId) {
            return false;
        }

        $belongsToWorkspace = (bool) ($order->workspace?->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists());

        $status = $order->status instanceof PaymentOrderStatus
            ? $order->status
            : PaymentOrderStatus::tryFrom((string) $order->status);

        return ($order->user_id === $user->id || $belongsToWorkspace)
            && in_array($status, [PaymentOrderStatus::PENDING_PAYMENT, PaymentOrderStatus::PENDING_VERIFICATION], true);
    }

    public function cancel(User $user, PaymentOrder $order): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        $currentWorkspaceId = app()->bound('current_workspace_id') ? (int) app('current_workspace_id') : null;
        if ($currentWorkspaceId && (int) $order->workspace_id !== $currentWorkspaceId) {
            return false;
        }

        $belongsToWorkspace = (bool) ($order->workspace?->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists());

        $status = $order->status instanceof PaymentOrderStatus
            ? $order->status
            : PaymentOrderStatus::tryFrom((string) $order->status);

        return ($order->user_id === $user->id || $belongsToWorkspace)
            && in_array($status, [PaymentOrderStatus::PENDING_PAYMENT, PaymentOrderStatus::PENDING_VERIFICATION], true);
    }

    public function approve(User $user, PaymentOrder $order): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function reject(User $user, PaymentOrder $order): bool
    {
        return $this->isPlatformAdmin($user);
    }
}
