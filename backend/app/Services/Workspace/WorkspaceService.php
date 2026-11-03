<?php

namespace App\Services\Workspace;

use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\CreditAccount;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Security\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorkspaceService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Auto-provision a new Workspace and bridge Tenant for a user.
     */
    public function createWorkspaceForUser(User $user, string $name, ?string $slug = null): Workspace
    {
        return DB::transaction(function () use ($user, $name, $slug) {
            $baseSlug = Str::slug($slug ?: $name);
            $uniqueSlug = $baseSlug.'-'.Str::lower(Str::random(6));

            // 1. Create bridge Tenant for backward compatibility
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $uniqueSlug,
                'plan' => 'starter',
                'is_active' => true,
            ]);

            // 2. Create Workspace
            $workspace = Workspace::create([
                'owner_id' => $user->id,
                'legacy_tenant_id' => $tenant->id,
                'name' => $name,
                'slug' => $uniqueSlug,
                'status' => WorkspaceStatus::ACTIVE,
                'settings' => [],
            ]);

            // 3. Create Workspace Member as Owner
            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceMemberRole::OWNER,
                'invited_by' => null,
                'joined_at' => now(),
                'is_active' => true,
            ]);

            // 4. Update user's default workspace and tenant
            $user->tenant_id = $tenant->id;
            $user->default_workspace_id = $workspace->id;
            $user->save();

            // 5. Initialize Credit Account (100 free credits daily limit for Free Plan)
            CreditAccount::create([
                'workspace_id' => $workspace->id,
                'available_credits' => 100,
                'reserved_credits' => 0,
                'cycle_granted_credits' => 100,
                'cycle_used_credits' => 0,
                'period_start' => now(),
                'period_end' => now()->addDay(),
                'version' => 1,
            ]);

            // 6. Connect Free Plan Subscription if Plan exists
            $freePlan = Plan::where('code', PlanCode::FREE->value)->first();
            if ($freePlan) {
                Subscription::create([
                    'workspace_id' => $workspace->id,
                    'plan_id' => $freePlan->id,
                    'status' => SubscriptionStatus::ACTIVE,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addDay(),
                    'last_credit_granted_at' => now(),
                    'next_credit_grant_at' => now()->addDay(),
                ]);
            }

            // 7. Audit Log
            $this->auditLogService->log(
                action: 'workspace.created',
                auditable: $workspace,
                newValues: ['name' => $name, 'slug' => $uniqueSlug, 'owner_id' => $user->id],
                workspaceId: $workspace->id,
                actor: $user
            );

            return $workspace;
        });
    }

    /**
     * Switch user's active/default workspace.
     */
    public function switchWorkspace(User $user, int $workspaceId): Workspace
    {
        $workspace = Workspace::where('id', $workspaceId)->firstOrFail();

        $isMember = $workspace->members()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $isMember && ! $user->is_platform_admin) {
            throw new InvalidArgumentException('Bạn không phải là thành viên hoạt động của Workspace này.');
        }

        if ($workspace->status === WorkspaceStatus::SUSPENDED) {
            throw new InvalidArgumentException('Workspace này đang bị tạm ngưng.');
        }

        $user->default_workspace_id = $workspace->id;
        if ($workspace->legacy_tenant_id) {
            $user->tenant_id = $workspace->legacy_tenant_id;
        }
        $user->save();

        $this->auditLogService->log(
            action: 'workspace.switched',
            auditable: $workspace,
            newValues: ['default_workspace_id' => $workspace->id],
            workspaceId: $workspace->id,
            actor: $user
        );

        return $workspace;
    }

    /**
     * Add a member to the workspace.
     */
    public function addMember(Workspace $workspace, User $user, WorkspaceMemberRole $role = WorkspaceMemberRole::EDITOR, ?User $inviter = null): WorkspaceMember
    {
        $member = WorkspaceMember::updateOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id],
            [
                'role' => $role,
                'invited_by' => $inviter?->id,
                'joined_at' => now(),
                'is_active' => true,
            ]
        );

        $this->auditLogService->log(
            action: 'workspace.member_added',
            auditable: $workspace,
            newValues: ['user_id' => $user->id, 'role' => $role->value],
            workspaceId: $workspace->id,
            actor: $inviter
        );

        return $member;
    }

    /**
     * Remove a member from the workspace.
     */
    public function removeMember(Workspace $workspace, User $user, ?User $actor = null): bool
    {
        if ($workspace->owner_id === $user->id) {
            throw new InvalidArgumentException('Không thể xóa chủ sở hữu khỏi Workspace.');
        }

        $deleted = $workspace->members()->where('user_id', $user->id)->delete() > 0;

        if ($deleted) {
            if ($user->default_workspace_id === $workspace->id) {
                $anotherWorkspace = $user->workspaces()->where('is_active', true)->first();
                $user->default_workspace_id = $anotherWorkspace?->id;
                $user->tenant_id = $anotherWorkspace?->legacy_tenant_id;
                $user->save();
            }

            $this->auditLogService->log(
                action: 'workspace.member_removed',
                auditable: $workspace,
                oldValues: ['user_id' => $user->id],
                workspaceId: $workspace->id,
                actor: $actor
            );
        }

        return $deleted;
    }

    /**
     * Get all active workspaces accessible by user.
     */
    public function getUserWorkspaces(User $user): Collection
    {
        if ($user->is_platform_admin) {
            return Workspace::with(['owner:id,name,email', 'creditAccount'])->get();
        }

        return $user->workspaces()
            ->with(['owner:id,name,email', 'creditAccount'])
            ->where('workspace_members.is_active', true)
            ->get();
    }
}
