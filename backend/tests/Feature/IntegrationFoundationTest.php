<?php

namespace Tests\Feature;

use App\DTOs\Integrations\GoogleAuthDTO;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Jobs\BaseSyncJob;
use App\Models\GoogleAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Integrations\GoogleOAuthService;
use App\Services\Integrations\TokenVaultService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Tenant $tenantA;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();

        config([
            'services.google.client_id' => 'test-google-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-google-client-secret-xyz',
            'services.google.redirect_uri' => 'http://localhost:8000/api/v1/integrations/google/callback',
        ]);

        // 1. Setup Tenant & Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'email' => 'usera@example.com',
            'tenant_id' => $this->tenantA->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceA = Workspace::create([
            'owner_id' => $this->userA->id,
            'legacy_tenant_id' => $this->tenantA->id,
            'name' => 'Workspace A',
            'slug' => 'workspace-a',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->userA->update(['default_workspace_id' => $this->workspaceA->id]);

        // 2. Setup Tenant & Workspace B
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'plan' => 'pro',
            'is_active' => true,
        ]);

        $this->userB = User::factory()->create([
            'email' => 'userb@example.com',
            'tenant_id' => $this->tenantB->id,
            'role' => UserRole::ADMIN,
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'owner_id' => $this->userB->id,
            'legacy_tenant_id' => $this->tenantB->id,
            'name' => 'Workspace B',
            'slug' => 'workspace-b',
            'status' => WorkspaceStatus::ACTIVE,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'role' => WorkspaceMemberRole::OWNER,
            'is_active' => true,
        ]);

        $this->userB->update(['default_workspace_id' => $this->workspaceB->id]);
    }

    public function test_google_oauth_service_builds_auth_url_with_required_scopes(): void
    {
        $oauthService = app(GoogleOAuthService::class);
        $state = 'test_random_state_12345';
        $authUrl = $oauthService->getAuthUrl($state);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $authUrl);
        $this->assertStringContainsString('client_id=test-google-client-id.apps.googleusercontent.com', $authUrl);
        $this->assertStringContainsString('state=test_random_state_12345', $authUrl);
        $this->assertStringContainsString('response_type=code', $authUrl);
        $this->assertStringContainsString('access_type=offline', $authUrl);

        // Required Scopes Verification
        $decodedUrl = urldecode($authUrl);
        $this->assertStringContainsString('https://www.googleapis.com/auth/webmasters.readonly', $decodedUrl);
        $this->assertStringContainsString('https://www.googleapis.com/auth/analytics.readonly', $decodedUrl);
        $this->assertStringContainsString('openid', $decodedUrl);
    }

    public function test_google_oauth_auth_url_endpoint_returns_url_and_stores_state_in_cache(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->getJson('/api/v1/integrations/google/auth-url');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'auth_url',
                'state',
            ]);

        $state = $response->json('state');
        $this->assertNotEmpty($state);

        // Verify cache storage
        $cachedData = Cache::get("google_oauth_state:{$state}");
        $this->assertNotNull($cachedData);
        $this->assertEquals($this->workspaceA->id, $cachedData['workspace_id']);
        $this->assertEquals($this->userA->id, $cachedData['user_id']);
    }

    public function test_google_oauth_callback_exchanges_code_for_tokens_and_stores_account(): void
    {
        Sanctum::actingAs($this->userA);

        $state = 'valid_oauth_state_abc123';
        Cache::put("google_oauth_state:{$state}", [
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
        ], now()->addMinutes(10));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_google_access_token_xyz',
                'refresh_token' => 'mock_google_refresh_token_uvw',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'openid https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly',
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google_sub_10987654321',
                'email' => 'seo-manager@auraseo.io',
                'name' => 'Aura SEO Manager',
                'picture' => 'https://example.com/avatar.png',
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson('/api/v1/integrations/google/callback', [
            'code' => 'mock_authorization_code_456',
            'state' => $state,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Google account connected successfully.',
                'account' => [
                    'email' => 'seo-manager@auraseo.io',
                    'google_id' => 'google_sub_10987654321',
                    'status' => 'active',
                ],
            ]);

        // Verify DB Persistence
        $account = GoogleAccount::withoutGlobalScopes()
            ->where('workspace_id', $this->workspaceA->id)
            ->where('google_id', 'google_sub_10987654321')
            ->first();

        $this->assertNotNull($account);
        $this->assertEquals('seo-manager@auraseo.io', $account->email);
        $this->assertEquals($this->userA->id, $account->user_id);
        $this->assertEquals('mock_google_access_token_xyz', $account->access_token);
        $this->assertEquals('mock_google_refresh_token_uvw', $account->refresh_token);
        $this->assertTrue($account->hasScope('https://www.googleapis.com/auth/webmasters.readonly'));
        $this->assertTrue($account->hasScope('https://www.googleapis.com/auth/analytics.readonly'));

        // Verify state is removed from cache to prevent replay
        $this->assertNull(Cache::get("google_oauth_state:{$state}"));
    }

    public function test_google_oauth_callback_rejects_invalid_or_expired_state(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->postJson('/api/v1/integrations/google/callback', [
            'code' => 'some_code',
            'state' => 'non_existent_or_expired_state',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_google_account_tokens_are_encrypted_in_database(): void
    {
        BelongsToWorkspace::setExecutionContext($this->workspaceA->id);

        $plainAccessToken = 'ya29.a0AfH6SMB_secret_access_token_sample_string';
        $plainRefreshToken = '1//0gXYZ_secret_refresh_token_sample_string';

        $account = GoogleAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'google_id' => 'google_id_encrypt_test_999',
            'email' => 'encrypt-test@auraseo.io',
            'access_token' => $plainAccessToken,
            'refresh_token' => $plainRefreshToken,
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid', 'webmasters.readonly'],
            'status' => 'active',
        ]);

        // Raw SQL Query to inspect actual DB stored bytes
        $raw = DB::table('google_accounts')->where('id', $account->id)->first();
        $this->assertNotNull($raw);

        // Tokens in DB MUST NOT match plaintext
        $this->assertNotEquals($plainAccessToken, $raw->access_token, 'Access token is stored in plaintext in the database!');
        $this->assertNotEquals($plainRefreshToken, $raw->refresh_token, 'Refresh token is stored in plaintext in the database!');

        // Eloquent accessor MUST transparently decrypt them
        $freshAccount = GoogleAccount::find($account->id);
        $this->assertEquals($plainAccessToken, $freshAccount->access_token);
        $this->assertEquals($plainRefreshToken, $freshAccount->refresh_token);

        BelongsToWorkspace::setExecutionContext(null);
    }

    public function test_google_account_tokens_are_never_exposed_in_json_or_api_responses(): void
    {
        BelongsToWorkspace::setExecutionContext($this->workspaceA->id);

        $account = GoogleAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'google_id' => 'google_id_leak_check_888',
            'email' => 'security-audit@auraseo.io',
            'access_token' => 'super_secret_access_token',
            'refresh_token' => 'super_secret_refresh_token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        // 1. Test Model Serialization
        $array = $account->toArray();
        $this->assertArrayNotHasKey('access_token', $array);
        $this->assertArrayNotHasKey('refresh_token', $array);

        $json = $account->toJson();
        $this->assertStringNotContainsString('super_secret_access_token', $json);
        $this->assertStringNotContainsString('super_secret_refresh_token', $json);

        // 2. Test API Status Endpoint
        Sanctum::actingAs($this->userA);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->getJson('/api/v1/integrations/google/status');

        $response->assertStatus(200);
        $responseContent = $response->getContent();
        $this->assertStringNotContainsString('super_secret_access_token', $responseContent);
        $this->assertStringNotContainsString('super_secret_refresh_token', $responseContent);

        BelongsToWorkspace::setExecutionContext(null);
    }

    public function test_tenant_isolation_prevents_cross_workspace_access(): void
    {
        // Setup Google Account on Workspace A
        BelongsToWorkspace::setExecutionContext($this->workspaceA->id);

        $accountA = GoogleAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'google_id' => 'account_of_workspace_a',
            'email' => 'tenant-a@auraseo.io',
            'access_token' => 'token_workspace_a',
            'refresh_token' => 'refresh_workspace_a',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        BelongsToWorkspace::setExecutionContext(null);

        // User B from Workspace B tries to disconnect Account A -> Must return 404
        Sanctum::actingAs($this->userB);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->deleteJson("/api/v1/integrations/google/{$accountA->id}");

        $response->assertStatus(404);

        // Workspace B checks integration status -> must report connected: false
        $statusResponse = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceB->id,
        ])->getJson('/api/v1/integrations/google/status');

        $statusResponse->assertStatus(200)
            ->assertJson([
                'connected' => false,
                'account' => null,
            ]);

        // Account A must still exist safely in Workspace A
        $this->assertDatabaseHas('google_accounts', [
            'id' => $accountA->id,
            'workspace_id' => $this->workspaceA->id,
            'status' => 'active',
        ]);
    }

    public function test_token_vault_service_encrypts_and_decrypts_tokens_properly(): void
    {
        $vault = app(TokenVaultService::class);
        $secret = 'raw_api_token_payload_to_encrypt';

        $encrypted = $vault->encryptToken($secret);
        $this->assertNotEquals($secret, $encrypted);

        $decrypted = $vault->decryptToken($encrypted);
        $this->assertEquals($secret, $decrypted);
    }

    public function test_google_oauth_service_auto_refreshes_token_when_expiring_soon(): void
    {
        BelongsToWorkspace::setExecutionContext($this->workspaceA->id);

        $account = GoogleAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'google_id' => 'auto_refresh_google_id',
            'email' => 'auto-refresh@auraseo.io',
            'access_token' => 'old_access_token_about_to_expire',
            'refresh_token' => 'valid_refresh_token_to_use',
            'token_expires_at' => Carbon::now()->addSeconds(60), // Expiring in 1 minute (< 300s threshold)
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'brand_new_refreshed_access_token_123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $oauthService = app(GoogleOAuthService::class);
        $updatedAccount = $oauthService->refreshTokenIfNeeded($account);

        $this->assertEquals('brand_new_refreshed_access_token_123', $updatedAccount->access_token);
        $this->assertTrue($updatedAccount->token_expires_at->isFuture());
        $this->assertGreaterThan(now()->addMinutes(50), $updatedAccount->token_expires_at);

        BelongsToWorkspace::setExecutionContext(null);
    }

    public function test_base_sync_job_respects_distributed_lock_and_tenant_context(): void
    {
        $executed = false;
        $capturedContextWorkspaceId = null;

        $syncJob = new class($this->workspaceA->id, $executed, $capturedContextWorkspaceId) extends BaseSyncJob
        {
            public function __construct(
                int $workspaceId,
                public bool &$executedRef,
                public ?int &$contextRef
            ) {
                parent::__construct($workspaceId);
            }

            public function getIdempotencyKey(): string
            {
                return "test-sync-job:{$this->workspaceId}:2026-11-07";
            }

            protected function executeSync(): void
            {
                $this->executedRef = true;
                $this->contextRef = BelongsToWorkspace::getExecutionContext();
            }
        };

        // Execute the job
        $syncJob->handle();

        $this->assertTrue($executed, 'BaseSyncJob::executeSync() was not executed.');
        $this->assertEquals($this->workspaceA->id, $capturedContextWorkspaceId, 'Tenant execution context was not set during sync.');

        // After completion, execution context must be cleared
        $this->assertNull(BelongsToWorkspace::getExecutionContext());
    }

    public function test_google_auth_dto_converts_arrays_correctly(): void
    {
        $dto = GoogleAuthDTO::fromArray([
            'google_id' => 'gid_12345',
            'email' => 'dto@auraseo.io',
            'access_token' => 'access_xyz',
            'refresh_token' => 'refresh_xyz',
            'expires_in' => 3600,
            'scopes' => ['openid', 'webmasters.readonly'],
        ]);

        $this->assertEquals('gid_12345', $dto->googleId);
        $this->assertEquals('dto@auraseo.io', $dto->email);
        $this->assertEquals('access_xyz', $dto->accessToken);
        $this->assertEquals('refresh_xyz', $dto->refreshToken);
        $this->assertNotNull($dto->tokenExpiresAt);

        $safe = $dto->toSafeArray();
        $this->assertArrayNotHasKey('access_token', $safe);
        $this->assertArrayNotHasKey('refresh_token', $safe);
        $this->assertEquals('dto@auraseo.io', $safe['email']);
    }

    public function test_google_account_disconnect_endpoint(): void
    {
        BelongsToWorkspace::setExecutionContext($this->workspaceA->id);

        $account = GoogleAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'google_id' => 'disconnect_test_account',
            'email' => 'disconnect@auraseo.io',
            'access_token' => 'token_to_disconnect',
            'refresh_token' => 'refresh_to_disconnect',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        BelongsToWorkspace::setExecutionContext(null);

        Http::fake([
            'https://oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->withHeaders([
            'X-Workspace-Id' => (string) $this->workspaceA->id,
        ])->deleteJson("/api/v1/integrations/google/{$account->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Google account disconnected successfully.',
            ]);

        $this->assertDatabaseMissing('google_accounts', [
            'id' => $account->id,
        ]);
    }
}
