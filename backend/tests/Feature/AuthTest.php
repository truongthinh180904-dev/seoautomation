<?php

namespace Tests\Feature;

use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\CreditAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();
        RateLimiter::clear('login:*');
    }

    protected function tearDown(): void
    {
        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();
        parent::tearDown();
    }

    public function test_user_can_register_and_automatically_provisions_workspace_and_credit_account(): void
    {
        $this->seed(PlanSeeder::class);

        $payload = [
            'name' => 'Nguyễn Văn A',
            'email' => 'nguyenvana@example.com',
            'password' => 'SecurePass123@',
            'password_confirmation' => 'SecurePass123@',
            'workspace_name' => 'Agency Alpha',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'default_workspace_id',
                    ],
                    'token',
                    'token_type',
                ],
            ]);

        $user = User::where('email', 'nguyenvana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->default_workspace_id);

        $workspace = Workspace::where('id', $user->default_workspace_id)->first();
        $this->assertNotNull($workspace);
        $this->assertEquals('Agency Alpha', $workspace->name);
        $this->assertEquals($user->id, $workspace->owner_id);

        $membership = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($membership);
        $this->assertEquals(WorkspaceMemberRole::OWNER, $membership->role);
        $this->assertTrue($membership->is_active);

        $creditAccount = CreditAccount::where('workspace_id', $workspace->id)->first();
        $this->assertNotNull($creditAccount);
        $this->assertEquals(100, $creditAccount->available_credits);
    }

    public function test_registration_requires_valid_data_and_unique_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'existing@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        $workspace = Workspace::factory()->create([
            'owner_id' => $user->id,
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $user->update(['default_workspace_id' => $workspace->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'email'],
                    'token',
                    'token_type',
                ],
            ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('CorrectPassword'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rate_limiting_blocks_brute_force_after_5_attempts(): void
    {
        User::factory()->create([
            'email' => 'target@example.com',
            'password' => Hash::make('TargetPassword'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson('/api/v1/auth/login', [
                'email' => 'target@example.com',
                'password' => 'WrongPassword',
            ]);
            $res->assertStatus(422);
        }

        // 6th attempt should be blocked by rate limiter
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'target@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(429);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ACCOUNT_INACTIVE');
    }

    public function test_user_with_suspended_workspace_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended_user@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $workspace = Workspace::factory()->create([
            'owner_id' => $user->id,
            'status' => WorkspaceStatus::SUSPENDED,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $user->update(['default_workspace_id' => $workspace->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended_user@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'WORKSPACE_SUSPENDED');
    }

    public function test_user_can_logout_and_revokes_token(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_forgot_password_and_reset_password_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@example.com',
            'password' => Hash::make('OldPassword123'),
        ]);

        $forgotResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'resetme@example.com',
        ]);

        $forgotResponse->assertStatus(200);
        $resetToken = $forgotResponse->json('reset_token');
        $this->assertNotEmpty($resetToken);

        $resetResponse = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'resetme@example.com',
            'token' => $resetToken,
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $resetResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('BrandNewPassword123!', $user->fresh()->password));

        // Old password no longer works
        $loginFail = $this->postJson('/api/v1/auth/login', [
            'email' => 'resetme@example.com',
            'password' => 'OldPassword123',
        ]);
        $loginFail->assertStatus(422);

        // New password works
        $loginSuccess = $this->postJson('/api/v1/auth/login', [
            'email' => 'resetme@example.com',
            'password' => 'BrandNewPassword123!',
        ]);
        $loginSuccess->assertStatus(200);
    }

    public function test_authenticated_user_can_get_profile_with_workspace_details(): void
    {
        $user = User::factory()->create([
            'email' => 'me_profile@example.com',
            'is_active' => true,
        ]);

        $workspace = Workspace::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Profile Workspace',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $user->update(['default_workspace_id' => $workspace->id]);
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'me_profile@example.com')
            ->assertJsonPath('data.default_workspace_id', $workspace->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'default_workspace_id',
                    'default_workspace' => [
                        'id',
                        'name',
                        'slug',
                        'status',
                    ],
                    'workspaces',
                ],
            ]);
    }
}
