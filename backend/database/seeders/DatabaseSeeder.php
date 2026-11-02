<?php

namespace Database\Seeders;

use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\CreditAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Idempotent Tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Tenant',
                'plan' => 'starter',
                'is_active' => true,
            ]
        );

        // 2. Idempotent Platform Super Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'role' => 'super_admin',
                'is_platform_admin' => true,
                'is_active' => true,
            ]
        );
        $admin->role = 'super_admin';
        $admin->is_platform_admin = true;
        $admin->save();

        // 3. Idempotent Primary Workspace
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'default-workspace'],
            [
                'owner_id' => $admin->id,
                'legacy_tenant_id' => $tenant->id,
                'name' => 'Default Workspace',
                'status' => WorkspaceStatus::ACTIVE,
            ]
        );

        // 4. Ensure Membership
        WorkspaceMember::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $admin->id,
            ],
            [
                'role' => WorkspaceMemberRole::OWNER,
                'joined_at' => now(),
                'is_active' => true,
            ]
        );

        // 5. Ensure Credit Account
        CreditAccount::firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'available_credits' => 100,
                'reserved_credits' => 0,
                'cycle_granted_credits' => 100,
                'cycle_used_credits' => 0,
                'version' => 1,
            ]
        );

        // 6. Set user default workspace
        if (! $admin->default_workspace_id) {
            $admin->default_workspace_id = $workspace->id;
            $admin->save();
        }

        // 7. Seed Plans & Prompts
        $this->call([
            PlanSeeder::class,
            SubscriptionPlanSeeder::class,
            AIPromptSeeder::class,
        ]);
    }
}
