<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\CampaignStatus;
use App\Enums\KeywordStatus;
use App\Enums\MediaAssetStatus;
use App\Enums\MediaSourceType;
use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Jobs\SERP\SerpResearchJob;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\Keyword;
use App\Models\MediaAsset;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WordPressSite;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
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

        // 1. Setup Tenant & Workspace A
        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'plan' => 'starter',
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
            'plan' => 'starter',
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

    protected function tearDown(): void
    {
        BelongsToTenant::setExecutionContext(null);
        BelongsToWorkspace::setExecutionContext(null);
        app()->forgetInstance('current_tenant_id');
        app()->forgetInstance('current_workspace_id');
        Cache::flush();
        parent::tearDown();
    }

    public function test_user_a_cannot_view_or_update_or_delete_articles_of_workspace_b(): void
    {
        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'Từ khóa của Tenant B',
            'status' => KeywordStatus::PENDING,
        ]);

        $articleB = Article::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'keyword_id' => $keywordB->id,
            'user_id' => $this->userB->id,
            'title' => 'Bí mật của Tenant B',
            'slug' => 'bi-mat-cua-tenant-b',
            'status' => ArticleStatus::DRAFT,
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // User A attempts to view Article B
        $responseShow = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/articles/{$articleB->id}");
        $responseShow->assertStatus(404);

        // User A attempts to update Article B
        $responseUpdate = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/articles/{$articleB->id}", [
                'title' => 'Hacked title by Tenant A',
            ]);
        $responseUpdate->assertStatus(404);

        // User A attempts to delete Article B
        $responseDelete = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->deleteJson("/api/v1/articles/{$articleB->id}");
        $responseDelete->assertStatus(404);

        $this->assertDatabaseHas('articles', ['id' => $articleB->id, 'title' => 'Bí mật của Tenant B']);
    }

    public function test_user_a_cannot_view_or_access_campaigns_of_workspace_b(): void
    {
        $campaignB = Campaign::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->userB->id,
            'name' => 'Chiến dịch tuyệt mật B',
            'slug' => 'chien-dich-b',
            'status' => CampaignStatus::READY,
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // View Campaign B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/campaigns/{$campaignB->id}")
            ->assertStatus(404);

        // Update Campaign B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/campaigns/{$campaignB->id}", ['name' => 'Hacked Campaign B'])
            ->assertStatus(404);

        // Delete Campaign B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->deleteJson("/api/v1/campaigns/{$campaignB->id}")
            ->assertStatus(404);

        // Stats Campaign B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/campaigns/{$campaignB->id}/stats")
            ->assertStatus(404);

        $this->assertDatabaseHas('campaigns', ['id' => $campaignB->id, 'name' => 'Chiến dịch tuyệt mật B']);
    }

    public function test_user_a_cannot_view_or_manipulate_wordpress_sites_of_workspace_b(): void
    {
        $siteB = WordPressSite::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Website của Tenant B',
            'url' => 'https://tenant-b-site.com',
            'api_url' => 'https://tenant-b-site.com/wp-json',
            'username' => 'admin_b',
            'app_password' => 'secret-app-password-b',
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // View Site B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/wordpress-sites/{$siteB->id}")
            ->assertStatus(404);

        // Update Site B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/wordpress-sites/{$siteB->id}", ['name' => 'Hacked Site B'])
            ->assertStatus(404);

        // Delete Site B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->deleteJson("/api/v1/wordpress-sites/{$siteB->id}")
            ->assertStatus(404);

        // Test Site B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/wordpress-sites/{$siteB->id}/test")
            ->assertStatus(404);

        $this->assertDatabaseHas('wordpress_sites', ['id' => $siteB->id, 'name' => 'Website của Tenant B']);
    }

    public function test_idor_prevention_on_store_campaign_with_foreign_wordpress_site_id(): void
    {
        $siteB = WordPressSite::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Website của Tenant B',
            'url' => 'https://tenant-b-site.com',
            'api_url' => 'https://tenant-b-site.com/wp-json',
            'username' => 'admin_b',
            'app_password' => 'secret-b',
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // User A tries to create a campaign pointing to Tenant B's WordPress Site
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/campaigns', [
                'name' => 'Campaign Xâm Nhập',
                'wordpress_site_id' => $siteB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['wordpress_site_id']);
    }

    public function test_idor_prevention_on_store_keyword_with_foreign_campaign_or_wordpress_site(): void
    {
        $campaignB = Campaign::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->userB->id,
            'name' => 'Campaign B',
            'slug' => 'campaign-b',
            'status' => CampaignStatus::READY,
        ]);

        $siteB = WordPressSite::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Site B',
            'url' => 'https://site-b.com',
            'api_url' => 'https://site-b.com/wp-json',
            'username' => 'admin_b',
            'app_password' => 'pwd-b',
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // Try foreign campaign
        $resCampaign = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/keywords', [
                'keyword' => 'Test Keyword',
                'campaign_id' => $campaignB->id,
            ]);
        $resCampaign->assertStatus(422)->assertJsonValidationErrors(['campaign_id']);

        // Try foreign wordpress site
        $resSite = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/keywords', [
                'keyword' => 'Test Keyword 2',
                'wordpress_site_id' => $siteB->id,
            ]);
        $resSite->assertStatus(422)->assertJsonValidationErrors(['wordpress_site_id']);
    }

    public function test_idor_prevention_on_media_asset_upload_to_foreign_wordpress_site(): void
    {
        $siteB = WordPressSite::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Site B',
            'url' => 'https://site-b.com',
            'api_url' => 'https://site-b.com/wp-json',
            'username' => 'admin_b',
            'app_password' => 'pwd-b',
        ]);

        $assetA = MediaAsset::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'source_type' => 'external_url',
            'source_url' => 'https://images.unsplash.com/photo-sample.jpg',
            'status' => 'uploaded',
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // User A tries to upload asset A to Site B
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/media-assets/{$assetA->id}/upload-wordpress", [
                'wordpress_site_id' => $siteB->id,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['wordpress_site_id']);
    }

    public function test_non_admin_cannot_access_queue_status_or_retry(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/queue/status');
        $response->assertStatus(403);

        auth()->forgetGuards();

        $superAdmin = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email' => 'superadmin@example.com',
            'role' => UserRole::SUPER_ADMIN,
            'is_platform_admin' => true,
            'is_active' => true,
        ]);
        $tokenSuper = $superAdmin->createToken('token-super')->plainTextToken;

        auth()->forgetGuards();

        $responseSuper = $this->withHeader('Authorization', 'Bearer '.$tokenSuper)
            ->getJson('/api/v1/queue/status');
        $responseSuper->assertStatus(200);
    }

    public function test_wordpress_app_password_is_encrypted_and_never_exposed_via_api(): void
    {
        $site = WordPressSite::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Site Bảo Mật',
            'url' => 'https://mysite.com',
            'api_url' => 'https://mysite.com/wp-json',
            'username' => 'security_admin',
            'app_password' => 'super-secret-password-xyz',
        ]);

        // 1. Raw DB check: must not be stored in plaintext
        $rawPassword = DB::table('wordpress_sites')->where('id', $site->id)->value('app_password');
        $this->assertNotEquals('super-secret-password-xyz', $rawPassword);

        // 2. Eloquent model decodes it automatically
        $this->assertEquals('super-secret-password-xyz', $site->fresh()->app_password);

        // 3. API Response check: app_password must be hidden
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/wordpress-sites/{$site->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('app_password', $response->json('data'));
    }

    public function test_tenant_scope_isolation_in_query_builder_when_context_is_set(): void
    {
        WordPressSite::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Site A Only',
            'url' => 'https://site-a.com',
            'api_url' => 'https://site-a.com/wp-json',
            'username' => 'admin_a',
            'app_password' => 'pwd-a',
        ]);

        WordPressSite::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Site B Only',
            'url' => 'https://site-b.com',
            'api_url' => 'https://site-b.com/wp-json',
            'username' => 'admin_b',
            'app_password' => 'pwd-b',
        ]);

        // Set context to Tenant A
        BelongsToTenant::setExecutionContext($this->tenantA->id);

        $sitesForA = WordPressSite::all();
        $this->assertCount(1, $sitesForA);
        $this->assertEquals('Site A Only', $sitesForA->first()->name);

        // Reset context
        BelongsToTenant::setExecutionContext(null);
    }

    public function test_user_a_cannot_view_or_manipulate_websites_of_workspace_b(): void
    {
        $websiteB = Website::create([
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Website B Confidential',
            'domain' => 'b-confidential.com',
            'url' => 'https://b-confidential.com',
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // User A cannot view Website B
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/websites/{$websiteB->id}")
            ->assertStatus(404);

        // User A cannot update Website B
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/websites/{$websiteB->id}", ['name' => 'Hacked Website'])
            ->assertStatus(404);

        // User A cannot delete Website B
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->deleteJson("/api/v1/websites/{$websiteB->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('websites', ['id' => $websiteB->id, 'name' => 'Website B Confidential']);
    }

    public function test_website_creation_blocks_ssrf_urls(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/websites', [
                'name' => 'Localhost Exploit',
                'url' => 'http://127.0.0.1/admin',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_user_cannot_switch_to_unauthorized_workspace(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson("/api/v1/workspaces/{$this->workspaceB->id}/switch");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_user_cannot_add_members_to_unauthorized_workspace(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $otherUser = User::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson("/api/v1/workspaces/{$this->workspaceB->id}/members", [
                'user_id' => $otherUser->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_keyword_serp_endpoint_queues_job_for_authorized_tenant(): void
    {
        Queue::fake();

        $keywordA = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'Từ khóa SEO A',
            'status' => KeywordStatus::PENDING,
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/keywords/{$keywordA->id}/serp");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Queue::assertPushed(SerpResearchJob::class, function ($job) use ($keywordA) {
            return $job->keyword->id === $keywordA->id;
        });

        // User A cannot trigger SERP for keyword B
        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'Từ khóa SEO B',
            'status' => KeywordStatus::PENDING,
        ]);

        $responseB = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/keywords/{$keywordB->id}/serp");

        $responseB->assertStatus(404);
    }

    public function test_user_a_cannot_view_or_update_or_delete_keywords_of_workspace_b(): void
    {
        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'Từ khóa riêng tư của Tenant B',
            'status' => KeywordStatus::PENDING,
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // View Keyword B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/keywords/{$keywordB->id}")
            ->assertStatus(404);

        // Update Keyword B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/keywords/{$keywordB->id}", ['keyword' => 'Hacked Keyword'])
            ->assertStatus(404);

        // Delete Keyword B -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->deleteJson("/api/v1/keywords/{$keywordB->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('keywords', [
            'id' => $keywordB->id,
            'keyword' => 'Từ khóa riêng tư của Tenant B',
            'status' => KeywordStatus::PENDING,
        ]);
    }

    public function test_idor_prevention_on_generate_article_with_foreign_keyword(): void
    {
        $keywordB = Keyword::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'keyword' => 'Từ khóa B cho bài viết',
            'status' => KeywordStatus::PENDING,
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/articles/generate', [
                'keyword_id' => $keywordB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['keyword_id']);
    }

    public function test_idor_prevention_on_update_article_with_foreign_campaign(): void
    {
        $keywordA = Keyword::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'keyword' => 'Từ khóa hợp lệ A',
            'status' => KeywordStatus::PENDING,
        ]);

        $articleA = Article::create([
            'tenant_id' => $this->tenantA->id,
            'workspace_id' => $this->workspaceA->id,
            'keyword_id' => $keywordA->id,
            'user_id' => $this->userA->id,
            'title' => 'Bài viết của A',
            'slug' => 'bai-viet-cua-a',
            'status' => ArticleStatus::DRAFT,
        ]);

        $campaignB = Campaign::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'created_by' => $this->userB->id,
            'name' => 'Chiến dịch ngoài phạm vi B',
            'slug' => 'chien-dich-ngoai-b',
            'status' => CampaignStatus::READY,
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/articles/{$articleA->id}", [
                'campaign_id' => $campaignB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['campaign_id']);
    }

    public function test_user_a_cannot_view_or_access_workspace_b_details_or_members(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // User A cannot view Workspace B details
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/v1/workspaces/{$this->workspaceB->id}")
            ->assertStatus(403);

        // User A cannot view Workspace B members list
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/v1/workspaces/{$this->workspaceB->id}/members")
            ->assertStatus(403);
    }

    public function test_user_cannot_remove_members_from_unauthorized_workspace(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->deleteJson("/api/v1/workspaces/{$this->workspaceB->id}/members/{$this->userB->id}");

        $response->assertStatus(403);
    }

    public function test_user_a_cannot_manipulate_media_assets_of_workspace_b(): void
    {
        $assetB = MediaAsset::create([
            'tenant_id' => $this->tenantB->id,
            'workspace_id' => $this->workspaceB->id,
            'source_type' => MediaSourceType::UPLOADED,
            'local_path' => 'media/tenant-b-secret.png',
            'status' => MediaAssetStatus::UPLOADED,
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // User A attempts to update Media Asset B
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/media-assets/{$assetB->id}", [
                'alt_text' => 'Hacked Alt Text',
            ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('media_assets', [
            'id' => $assetB->id,
            'alt_text' => 'Hacked Alt Text',
        ]);
    }

    public function test_sanctum_token_expiration_is_strictly_configured(): void
    {
        $expiration = config('sanctum.expiration');

        $this->assertNotNull($expiration, 'Sanctum token expiration must not be null');
        $this->assertIsInt($expiration);
        $this->assertGreaterThanOrEqual(60, $expiration);
    }
}
