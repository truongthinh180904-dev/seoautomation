<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Chatbot\ConversationService;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConversationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;

    protected Workspace $workspaceA;

    protected Tenant $tenantA;

    protected Website $websiteA;

    protected User $userB;

    protected Workspace $workspaceB;

    protected Tenant $tenantB;

    protected Website $websiteB;

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

        $this->websiteA = Website::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Nha Khoa Smile Tech',
            'domain' => 'nhakhoasmile.vn',
            'url' => 'https://nhakhoasmile.vn',
        ]);

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

        $this->websiteB = Website::create([
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Thẩm Mỹ Viện Quốc Tế',
            'domain' => 'thammyvien.com',
            'url' => 'https://thammyvien.com',
        ]);
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

    public function test_can_create_conversation_for_workspace(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/conversations', [
                'title' => 'Nghiên cứu từ khóa răng sứ',
                'website_id' => $this->websiteA->id,
                'current_mode' => 'research',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Nghiên cứu từ khóa răng sứ')
            ->assertJsonPath('data.website_id', $this->websiteA->id)
            ->assertJsonPath('data.current_mode', 'research');

        $this->assertDatabaseHas('conversations', [
            'workspace_id' => $this->workspaceA->id,
            'title' => 'Nghiên cứu từ khóa răng sứ',
            'website_id' => $this->websiteA->id,
        ]);
    }

    public function test_can_list_conversations_filtered_by_workspace_and_website(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $convA1 = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'website_id' => $this->websiteA->id,
            'title' => 'Cuộc trò chuyện SEO 1',
            'current_mode' => 'general',
            'pinned' => true,
            'last_message_at' => now(),
        ]);

        $convA2 = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'website_id' => $this->websiteA->id,
            'title' => 'Cuộc trò chuyện Audit 2',
            'current_mode' => 'audit',
            'pinned' => false,
            'last_message_at' => now()->subHour(),
        ]);

        // Conversation of Workspace B
        Conversation::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'website_id' => $this->websiteB->id,
            'title' => 'Bí mật của Workspace B',
            'current_mode' => 'writing',
            'last_message_at' => now(),
        ]);

        // 1. User A lists all conversations
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson('/api/v1/conversations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Cuộc trò chuyện SEO 1', $titles);
        $this->assertContains('Cuộc trò chuyện Audit 2', $titles);
        $this->assertNotContains('Bí mật của Workspace B', $titles);

        // 2. Search filter
        $responseSearch = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson('/api/v1/conversations?search=Audit');

        $responseSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Cuộc trò chuyện Audit 2');
    }

    public function test_multi_tenancy_isolation_workspace_a_cannot_view_conversation_of_workspace_b(): void
    {
        $convB = Conversation::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'website_id' => $this->websiteB->id,
            'title' => 'Cuộc trò chuyện riêng tư của B',
            'current_mode' => 'general',
            'last_message_at' => now(),
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/conversations/{$convB->uuid}");

        $response->assertStatus(404);
    }

    public function test_multi_tenancy_isolation_workspace_a_cannot_update_or_delete_conversation_of_workspace_b(): void
    {
        $convB = Conversation::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'website_id' => $this->websiteB->id,
            'title' => 'Cuộc trò chuyện của B',
            'current_mode' => 'general',
            'last_message_at' => now(),
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // Try update
        $resUpdate = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/conversations/{$convB->uuid}", [
                'title' => 'Hacked by Workspace A',
            ]);
        $resUpdate->assertStatus(404);

        // Try delete
        $resDelete = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->deleteJson("/api/v1/conversations/{$convB->uuid}");
        $resDelete->assertStatus(404);

        $this->assertDatabaseHas('conversations', [
            'id' => $convB->id,
            'title' => 'Cuộc trò chuyện của B',
        ]);
    }

    public function test_idor_prevention_cannot_create_conversation_with_foreign_website_id(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson('/api/v1/conversations', [
                'title' => 'Hành vi vượt quyền Website',
                'website_id' => $this->websiteB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['website_id']);
    }

    public function test_can_send_and_retrieve_messages_in_conversation(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $convA = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'website_id' => $this->websiteA->id,
            'title' => 'Cuộc trò chuyện thử nghiệm',
            'current_mode' => 'general',
            'last_message_at' => now(),
        ]);

        // 1. Send user message with attached image info
        $resMsgUser = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/conversations/{$convA->uuid}/messages", [
                'role' => 'user',
                'content' => 'Phân tích Top 3 SERP cho từ khóa nha khoa thẩm mỹ',
                'images' => [
                    [
                        'id' => 'img-1',
                        'previewUrl' => 'https://example.com/sample.jpg',
                        'name' => 'sample.jpg',
                        'size' => 102400,
                        'mimeType' => 'image/jpeg',
                    ],
                ],
            ]);

        $resMsgUser->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.content', 'Phân tích Top 3 SERP cho từ khóa nha khoa thẩm mỹ');

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $convA->id,
            'role' => 'user',
        ]);

        // 2. Send assistant message with tool calls
        $resMsgAi = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/conversations/{$convA->uuid}/messages", [
                'role' => 'assistant',
                'content' => 'Dưới đây là phân tích 3 đối thủ Google SERP...',
                'tool_calls' => [
                    ['id' => 'tc-1', 'tool' => 'competitor_analysis', 'status' => 'completed'],
                ],
                'metadata' => [
                    'seo_score' => 92,
                ],
            ]);

        $resMsgAi->assertStatus(201)
            ->assertJsonPath('data.role', 'assistant');

        // 3. Retrieve messages list
        $resList = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/conversations/{$convA->uuid}/messages");

        $resList->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_multi_tenancy_isolation_workspace_a_cannot_send_or_read_messages_in_workspace_b_conversation(): void
    {
        $convB = Conversation::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'website_id' => $this->websiteB->id,
            'title' => 'Hội thoại bí mật B',
            'current_mode' => 'general',
            'last_message_at' => now(),
        ]);

        ConversationMessage::create([
            'conversation_id' => $convB->id,
            'workspace_id' => $this->workspaceB->id,
            'role' => 'user',
            'content' => 'Tin nhắn tuyệt mật của B',
        ]);

        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        // Try reading messages
        $resRead = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->getJson("/api/v1/conversations/{$convB->uuid}/messages");
        $resRead->assertStatus(404);

        // Try sending message
        $resSend = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/conversations/{$convB->uuid}/messages", [
                'role' => 'user',
                'content' => 'Tin nhắn phá hoại từ A',
            ]);
        $resSend->assertStatus(404);
    }

    public function test_first_user_message_automatically_updates_default_title(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $convA = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'website_id' => $this->websiteA->id,
            'title' => 'Cuộc trò chuyện mới',
            'current_mode' => 'general',
            'last_message_at' => now(),
        ]);

        $prompt = 'Hướng dẫn kỹ thuật trồng răng Implant chuẩn y khoa quốc tế';

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/conversations/{$convA->uuid}/messages", [
                'role' => 'user',
                'content' => $prompt,
            ])
            ->assertStatus(201);

        $convA->refresh();
        $this->assertNotEquals('Cuộc trò chuyện mới', $convA->title);
        $this->assertStringContainsString('Hướng dẫn kỹ thuật', $convA->title);
    }

    public function test_can_update_pinned_and_title_of_conversation(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $convA = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'website_id' => $this->websiteA->id,
            'title' => 'Tiêu đề ban đầu',
            'pinned' => false,
            'last_message_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->putJson("/api/v1/conversations/{$convA->uuid}", [
                'title' => 'Tiêu đề đã tối ưu',
                'pinned' => true,
                'current_mode' => 'writing',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Tiêu đề đã tối ưu')
            ->assertJsonPath('data.pinned', true)
            ->assertJsonPath('data.current_mode', 'writing');

        $this->assertDatabaseHas('conversations', [
            'id' => $convA->id,
            'title' => 'Tiêu đề đã tối ưu',
            'pinned' => true,
        ]);
    }

    public function test_can_clear_messages_in_conversation(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $convA = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'website_id' => $this->websiteA->id,
            'title' => 'Hội thoại sẽ bị xóa tin nhắn',
            'last_message_at' => now(),
        ]);

        ConversationMessage::create([
            'conversation_id' => $convA->id,
            'workspace_id' => $this->workspaceA->id,
            'role' => 'user',
            'content' => 'Tin nhắn 1',
        ]);
        ConversationMessage::create([
            'conversation_id' => $convA->id,
            'workspace_id' => $this->workspaceA->id,
            'role' => 'assistant',
            'content' => 'Tin nhắn 2',
        ]);

        $this->assertEquals(2, $convA->messages()->count());

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->postJson("/api/v1/conversations/{$convA->uuid}/clear");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(0, $convA->messages()->count());
        $this->assertDatabaseHas('conversations', ['id' => $convA->id]);
    }

    public function test_delete_conversation_cascades_and_removes_messages(): void
    {
        $tokenA = $this->userA->createToken('token-a')->plainTextToken;

        $convA = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'website_id' => $this->websiteA->id,
            'title' => 'Hội thoại chuẩn bị xóa hẳn',
            'last_message_at' => now(),
        ]);

        $msg = ConversationMessage::create([
            'conversation_id' => $convA->id,
            'workspace_id' => $this->workspaceA->id,
            'role' => 'user',
            'content' => 'Nội dung tin nhắn',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('X-Workspace-Id', $this->workspaceA->id)
            ->deleteJson("/api/v1/conversations/{$convA->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('conversations', ['id' => $convA->id]);
        $this->assertDatabaseMissing('conversation_messages', ['id' => $msg->id]);
    }

    public function test_conversation_service_get_recent_history_and_context_summary(): void
    {
        $service = app(ConversationService::class);

        $conv = $service->createConversation($this->workspaceA->id, $this->userA->id, [
            'title' => 'Test Service Conversation',
            'current_mode' => 'research',
            'context_summary' => 'Ngữ cảnh tóm tắt ban đầu về nha khoa',
        ]);

        for ($i = 1; $i <= 15; $i++) {
            $service->addMessage($conv, $i % 2 === 1 ? 'user' : 'assistant', "Tin nhắn test #{$i}");
        }

        $history = $service->getRecentHistory($conv, 10);

        $this->assertEquals('Ngữ cảnh tóm tắt ban đầu về nha khoa', $history['context_summary']);
        $this->assertEquals('research', $history['current_mode']);
        $this->assertCount(10, $history['messages']);

        // Check chronological order of the 10 recent messages: message 6 through 15
        $firstReturnedMsg = $history['messages']->first();
        $lastReturnedMsg = $history['messages']->last();
        $this->assertEquals('Tin nhắn test #6', $firstReturnedMsg->content);
        $this->assertEquals('Tin nhắn test #15', $lastReturnedMsg->content);

        // Update context summary
        $service->updateContextSummary($conv, 'Tóm tắt cập nhật mới');
        $this->assertEquals('Tóm tắt cập nhật mới', $conv->fresh()->context_summary);
    }
}
