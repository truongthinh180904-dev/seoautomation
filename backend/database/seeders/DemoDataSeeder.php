<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Enums\WebsiteStatus;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\Article;
use App\Models\BrandKnowledgeBase;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CreditAccount;
use App\Models\Ga4PagePerformanceDaily;
use App\Models\GscSearchPerformanceDaily;
use App\Models\Keyword;
use App\Models\RankTrackedKeyword;
use App\Models\RankTrackingHistory;
use App\Models\SeoIntelligenceInsight;
use App\Models\Tenant;
use App\Models\TopicCluster;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAutomationRule;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tạo Tenant cơ bản
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'AuraSEO Demo Tenant',
                'plan' => 'pro',
                'is_active' => true,
            ]
        );

        // 2. Tài khoản Super Admin dùng đăng nhập
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'AuraSEO Master',
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

        // 3. Workspace làm việc chính
        $workspace = Workspace::where('legacy_tenant_id', $tenant->id)->first()
            ?? Workspace::firstOrCreate(
                ['slug' => 'default-workspace'],
                [
                    'owner_id' => $admin->id,
                    'legacy_tenant_id' => $tenant->id,
                    'name' => 'Aura SEO Agency Workspace',
                    'status' => WorkspaceStatus::ACTIVE,
                ]
            );

        // Thành viên workspace
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

        $admin->default_workspace_id = $workspace->id;
        $admin->save();

        // 4. Cấp sẵn 500 Credits để thoải mái test
        $account = CreditAccount::firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'available_credits' => 500,
                'reserved_credits' => 0,
                'cycle_granted_credits' => 500,
                'cycle_used_credits' => 0,
                'version' => 1,
            ]
        );
        $account->available_credits = max(500, (int) $account->available_credits);
        $account->save();

        // 5. Tạo Website demo: Nha Khoa Smile Luxury
        $website = Website::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'domain' => 'nhakhoasmile.vn',
            ],
            [
                'name' => 'Nha Khoa Thẩm Mỹ Smile Luxury',
                'url' => 'https://nhakhoasmile.vn',
                'default_language' => 'vi',
                'niche' => 'Nha khoa thẩm mỹ & Chăm sóc răng miệng',
                'status' => WebsiteStatus::ACTIVE,
                'settings' => [
                    'target_audience' => 'Khách hàng 22-45 tuổi quan tâm bọc răng sứ, dán sứ veneer, niềng răng',
                    'primary_region' => 'Việt Nam (Hà Nội, TP.HCM)',
                ],
            ]
        );

        // 6. Cấu hình Brand Knowledge Base
        BrandKnowledgeBase::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
            ],
            [
                'brand_name' => 'Smile Luxury Dental Clinic',
                'tagline' => 'Kiến tạo nụ cười chuẩn tỷ lệ vàng',
                'tone_of_voice' => 'Chuyên gia y khoa, tận tâm, sang trọng, gần gũi và đáng tin cậy',
                'target_personas' => [
                    'Dân văn phòng, người trẻ muốn cải thiện nụ cười tự tin',
                    'Doanh nhân, người làm truyền thông cần thẩm mỹ răng cao cấp',
                ],
                'unique_selling_points' => [
                    'Công nghệ dán sứ Veneer siêu mỏng không mài răng thật',
                    'Đội ngũ thạc sĩ bác sĩ tu nghiệp chuyên sâu tại Đức và Thụy Sĩ',
                    'Chính sách bảo hành chính hãng điện tử lên đến 15 năm',
                ],
                'product_catalog' => [
                    'Bọc răng sứ Emax / Cercon HT / Lava Plus',
                    'Dán sứ Veneer Thụy Sĩ',
                    'Niềng răng trong suốt Invisalign',
                    'Cấy ghép Implant không đau',
                ],
                'taboo_words' => ['chặt chém', 'lừa đảo', 'giá rẻ như cho', 'cam kết 100% vĩnh viễn không biến chứng'],
                'preferred_terms' => ['chuẩn y khoa', 'bảo tồn răng thật', 'chính hãng', 'công nghệ Châu Âu'],
                'is_default' => true,
            ]
        );

        // 7. Tạo Topic Cluster
        $cluster = TopicCluster::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'slug' => 'cum-chu-de-boc-rang-su-tham-my',
            ],
            [
                'name' => 'Cụm chủ đề: Bọc Răng Sứ Thẩm Mỹ',
                'description' => 'Cụm nội dung trụ cột và bài viết vệ tinh bao phủ toàn bộ Search Intent về bọc sứ.',
                'target_audience' => 'Khách hàng thẩm mỹ răng 22-45 tuổi',
                'total_search_volume' => 12100,
                'avg_difficulty' => 23,
                'status' => 'active',
            ]
        );

        // 8. Tạo Danh sách Từ khóa Demo
        $kw1 = Keyword::firstOrCreate(
            ['workspace_id' => $workspace->id, 'website_id' => $website->id, 'keyword' => 'bọc răng sứ thẩm mỹ giá bao nhiêu'],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'topic_cluster_id' => $cluster->id,
                'language' => 'vi',
                'search_volume' => 5400,
                'difficulty' => 28,
                'cpc' => 1.25,
                'search_intent' => 'commercial',
                'status' => KeywordStatus::COMPLETED,
            ]
        );

        $kw2 = Keyword::firstOrCreate(
            ['workspace_id' => $workspace->id, 'website_id' => $website->id, 'keyword' => 'dán sứ veneer không mài răng'],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'topic_cluster_id' => $cluster->id,
                'language' => 'vi',
                'search_volume' => 3800,
                'difficulty' => 24,
                'cpc' => 1.50,
                'search_intent' => 'informational',
                'status' => KeywordStatus::PROCESSING,
            ]
        );

        $kw3 = Keyword::firstOrCreate(
            ['workspace_id' => $workspace->id, 'website_id' => $website->id, 'keyword' => 'bọc răng sứ có đau không'],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'topic_cluster_id' => $cluster->id,
                'language' => 'vi',
                'search_volume' => 2900,
                'difficulty' => 18,
                'cpc' => 0.85,
                'search_intent' => 'informational',
                'status' => KeywordStatus::PENDING,
            ]
        );

        // 9. Tạo Bài Viết Demo Chuẩn SEO & Bài Cần Làm Mới (Content Refresh)
        $article1 = Article::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'slug' => 'boc-rang-su-tham-my-gia-bao-nhieu-bang-gia-2026',
            ],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'keyword_id' => $kw1->id,
                'title' => 'Bọc Răng Sứ Thẩm Mỹ Giá Bao Nhiêu? Bảng Giá Mới Nhất 2026',
                'seo_title' => 'Bọc Răng Sứ Giá Bao Nhiêu 2026? Bảng Giá Chi Tiết & Top Loại Sứ Tốt',
                'seo_description' => 'Tìm hiểu chi phí bọc răng sứ thẩm mỹ 2026 trọn gói. So sánh sứ kim loại, toàn sứ Emax, Cercon và lưu ý để chọn địa chỉ nha khoa uy tín.',
                'focus_keyword' => 'bọc răng sứ thẩm mỹ giá bao nhiêu',
                'word_count' => 2450,
                'seo_score' => 95,
                'readability_score' => 90,
                'status' => ArticleStatus::PUBLISHED,
                'published_at' => now()->subDays(15),
                'content' => "## Bọc Răng Sứ Thẩm Mỹ Là Gì?\n\nBọc răng sứ thẩm mỹ là phương pháp phục hình nha khoa hiện đại, giúp cải thiện toàn diện về thẩm mỹ và chức năng ăn nhai cho răng khiếm khuyết.\n\n### Bảng Giá Bọc Răng Sứ Chi Tiết 2026\n\n| Dòng Răng Sứ | Xuất Xứ | Mức Giá Tham Khảo | Thời Gian Bảo Hành |\n| :--- | :---: | :---: | :---: |\n| Sứ Kim Loại Titan | Đức | 1.500.000 - 2.500.000 đ/răng | 5 - 7 năm |\n| Toàn Sứ Zirconia | Đức | 3.500.000 - 4.500.000 đ/răng | 10 năm |\n| Toàn Sứ Emax CAD/CAM | Thụy Sĩ | 5.500.000 - 7.000.000 đ/răng | 15 năm |\n| Sứ Cao Cấp Lava Plus | Mỹ | 8.000.000 - 10.000.000 đ/răng | Trọn đời |\n\n## Quy Trình Bọc Răng Sứ Chuẩn Y Khoa Tại Smile Luxury\n\n1. **Khám tổng quát và chụp phim CT Cone Beam 3D**\n2. **Vệ sinh khoang miệng và thiết kế nụ cười DSD (Digital Smile Design)**\n3. **Gây tê và sửa soạn cùi răng nhẹ nhàng không xâm lấn tủy**\n4. **Lấy dấu hàm kỹ thuật số và gắn răng tạm**\n5. **Gắn mão sứ cố định và hướng dẫn chăm sóc**\n\n## Câu Hỏi Thường Gặp (FAQs)\n\n**Bọc răng sứ có đau không?**\nNhờ công nghệ gây tê hiện đại và kỹ thuật mài bảo tồn, quá trình thực hiện hoàn toàn êm ái, không gây ê buốt kéo dài.",
            ]
        );

        $article2 = Article::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'slug' => 'dan-su-veneer-khong-mai-rang-uu-nhuoc-diem',
            ],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'keyword_id' => $kw2->id,
                'title' => 'Dán Sứ Veneer Không Mài Răng Có Tốt Không? Chi Tiết Ưu Nhược Điểm',
                'seo_title' => 'Dán Sứ Veneer Không Mài Răng: Có Tốt Không? Giá Mới Nhất',
                'seo_description' => 'Dán sứ Veneer là giải pháp thẩm mỹ răng bảo tồn tối đa răng thật. Cùng tìm hiểu ưu nhược điểm và chi phí thực hiện.',
                'focus_keyword' => 'dán sứ veneer không mài răng',
                'word_count' => 1650,
                'seo_score' => 74,
                'readability_score' => 78,
                'status' => ArticleStatus::DRAFT,
                'last_optimized_at' => now()->subDays(120),
                'optimization_notes' => 'Bài viết cũ thiếu thông tin so sánh với bọc sứ và chưa cập nhật bảng giá 2026. Cần AI Content Refresh!',
                'content' => "## Dán sứ Veneer là gì?\n\nDán sứ Veneer là kỹ thuật dán một lớp sứ mỏng khoảng 0.2 - 0.5mm lên bề mặt ngoài của răng.\n\n### Ưu điểm:\n- Bảo tồn răng gốc tối đa.\n- Không đau, không ê buốt.\n\n### Nhược điểm:\n- Giá thành tương đối cao.\n- Chỉ phù hợp với răng tương đối đều.",
            ]
        );

        // 10. Tạo Dữ liệu Rank Tracking (Thứ Hạng Từ Khóa Theo Ngày)
        $trackedKw1 = RankTrackedKeyword::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'keyword' => 'bọc răng sứ thẩm mỹ giá bao nhiêu',
            ],
            [
                'target_url' => 'https://nhakhoasmile.vn/boc-rang-su-tham-my-gia-bao-nhieu-bang-gia-2026',
                'country' => 'vn',
                'device' => 'desktop',
                'tracking_frequency' => 'daily',
                'current_position' => 2,
                'previous_position' => 4,
                'best_position' => 2,
                'last_checked_at' => now(),
                'is_active' => true,
            ]
        );

        $trackedKw2 = RankTrackedKeyword::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'keyword' => 'dán sứ veneer không mài răng',
            ],
            [
                'target_url' => 'https://nhakhoasmile.vn/dan-su-veneer-khong-mai-rang-uu-nhuoc-diem',
                'country' => 'vn',
                'device' => 'desktop',
                'tracking_frequency' => 'daily',
                'current_position' => 7,
                'previous_position' => 5,
                'best_position' => 4,
                'last_checked_at' => now(),
                'is_active' => true,
            ]
        );

        // Sinh lịch sử 7 ngày cho từ khóa
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            RankTrackingHistory::firstOrCreate(
                [
                    'rank_tracked_keyword_id' => $trackedKw1->id,
                    'check_date' => $date,
                ],
                [
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                    'position' => max(1, 2 + ($i % 3)),
                    'found_url' => 'https://nhakhoasmile.vn/boc-rang-su-tham-my-gia-bao-nhieu-bang-gia-2026',
                    'serp_features' => ['featured_snippet' => ($i === 0), 'people_also_ask' => true],
                ]
            );

            RankTrackingHistory::firstOrCreate(
                [
                    'rank_tracked_keyword_id' => $trackedKw2->id,
                    'check_date' => $date,
                ],
                [
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                    'position' => 5 + ($i % 4),
                    'found_url' => 'https://nhakhoasmile.vn/dan-su-veneer-khong-mai-rang-uu-nhuoc-diem',
                    'serp_features' => ['sitelinks' => true],
                ]
            );
        }

        // 11. Tạo Dữ liệu Lịch sử GSC & GA4 (30 Ngày) cho Dashboard Analytics
        $pageUrl1 = 'https://nhakhoasmile.vn/boc-rang-su-tham-my-gia-bao-nhieu-bang-gia-2026';
        $pageHash1 = md5($pageUrl1);
        $query1 = 'bọc răng sứ thẩm mỹ giá bao nhiêu';
        $queryHash1 = md5($query1);

        for ($d = 29; $d >= 0; $d--) {
            $metricDate = Carbon::now()->subDays($d)->toDateString();
            $baseClicks = 120 + ($d * 2) + rand(-15, 25);
            $baseImp = $baseClicks * 16 + rand(100, 300);

            // GSC
            GscSearchPerformanceDaily::firstOrCreate(
                [
                    'website_id' => $website->id,
                    'date' => $metricDate,
                    'page_hash' => $pageHash1,
                    'query_hash' => $queryHash1,
                    'country' => 'vnm',
                    'device' => 'DESKTOP',
                ],
                [
                    'workspace_id' => $workspace->id,
                    'query' => $query1,
                    'page_url' => $pageUrl1,
                    'clicks' => $baseClicks,
                    'impressions' => $baseImp,
                    'ctr' => round($baseClicks / max(1, $baseImp), 4),
                    'position' => round(2.5 + (sin($d) * 0.8), 2),
                ]
            );

            // GA4
            Ga4PagePerformanceDaily::firstOrCreate(
                [
                    'website_id' => $website->id,
                    'date' => $metricDate,
                    'page_hash' => $pageHash1,
                ],
                [
                    'workspace_id' => $workspace->id,
                    'article_id' => $article1->id,
                    'landing_page' => '/boc-rang-su-tham-my-gia-bao-nhieu-bang-gia-2026',
                    'sessions' => round($baseClicks * 1.15),
                    'active_users' => round($baseClicks * 0.95),
                    'engagement_rate' => 0.6850,
                    'conversions' => round($baseClicks * 0.04),
                    'avg_session_duration' => 185.50,
                ]
            );
        }

        // 12. Tạo SEO Intelligence Insights
        SeoIntelligenceInsight::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'type' => 'striking_distance',
            ],
            [
                'severity' => 'warning',
                'title' => 'Từ khóa "dán sứ veneer không mài răng" đang xếp hạng #7 cận trang 1',
                'description' => 'Từ khóa có 3.800 lượt tìm kiếm/tháng, vị trí hiện tại là #7. Bổ sung thêm 300 từ ngữ cảnh và 2 liên kết nội bộ có thể đẩy thẳng vào Top 3.',
                'metrics_snapshot' => ['position' => 7, 'search_volume' => 3800, 'current_ctr' => 0.042],
                'suggested_action' => 'Kích hoạt AI Content Refresh và chèn internal link từ bài trụ cột',
                'status' => 'detected',
            ]
        );

        SeoIntelligenceInsight::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'type' => 'traffic_decay',
            ],
            [
                'article_id' => $article2->id,
                'severity' => 'critical',
                'title' => 'Bài viết dán sứ veneer có dấu hiệu giảm 28% lượng clicks',
                'description' => 'Lượng truy cập 30 ngày qua giảm liên tục. Nội dung chưa cập nhật bảng giá và FAQ mới theo Google EEAT.',
                'metrics_snapshot' => ['clicks_drop_pct' => 28.5, 'days_stale' => 120],
                'suggested_action' => 'Mở Content Studio và bấm Tối ưu bài viết (AI Refresh)',
                'status' => 'detected',
            ]
        );

        // 13. Tạo Phiên Chatbot Mẫu Với Aura Assistant
        $conv = Conversation::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'uuid' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
            ],
            [
                'user_id' => $admin->id,
                'title' => 'Tư vấn chiến lược SEO Nha Khoa & Tối ưu bài viết',
                'current_mode' => 'general',
                'pinned' => true,
                'last_message_at' => now(),
            ]
        );

        ConversationMessage::firstOrCreate(
            [
                'conversation_id' => $conv->id,
                'workspace_id' => $workspace->id,
                'role' => 'user',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'content' => 'Chào Aura! Phân tích tình hình SEO tháng này của website nhakhoasmile.vn và kiểm tra xem có bài viết nào cần làm mới không?',
                'status' => 'completed',
            ]
        );

        ConversationMessage::firstOrCreate(
            [
                'conversation_id' => $conv->id,
                'workspace_id' => $workspace->id,
                'role' => 'assistant',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'content' => "Chào bạn! Mình là **Aura SEO Assistant**. Mình vừa quét dữ liệu từ Google Search Console, GA4 và Rank Tracker cho domain `nhakhoasmile.vn`:\n\n1. **Hiệu suất tổng quan (30 ngày qua):**\n   - Tổng lượt Click: **4.120** (tăng +14.5% so với tháng trước)\n   - Tổng Impressions: **68.400**\n   - CTR trung bình: **6.02%**\n   - Vị trí trung bình: **3.4**\n\n2. **Từ khóa nổi bật:**\n   - *'bọc răng sứ thẩm mỹ giá bao nhiêu'*: Đang đứng vững ở **Top 2 Google**.\n   - *'dán sứ veneer không mài răng'*: Đang ở vị trí **#7** (cơ hội bứt phá Top 3 rất cao).\n\n3. **Cảnh báo cần xử lý ngay:**\n   - Bài viết *'Dán Sứ Veneer Không Mài Răng'* đã hơn 120 ngày chưa cập nhật và bị giảm 28% clicks. Bạn có muốn mình tạo ngay một bản **AI Content Refresh** để xem trước bản so sánh Trước/Sau không?",
                'status' => 'completed',
            ]
        );

        // 14. Thiết lập Automation Rules cho Website
        WebsiteAutomationRule::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
            ],
            [
                'sync_gsc_enabled' => true,
                'sync_ga4_enabled' => true,
                'rank_tracking_enabled' => true,
                'anomaly_scan_enabled' => true,
                'alert_ranking_drop_threshold' => 3,
                'alert_traffic_drop_percentage' => 20,
                'email_notifications_enabled' => true,
            ]
        );

        $this->command->info('✅ Đã nạp thành công 100% dữ liệu Demo cho AuraSEO!');
        $this->command->info('👤 Tài khoản đăng nhập: admin@example.com / Mật khẩu: password');
        $this->command->info('🌐 Website demo: nhakhoasmile.vn');
        $this->command->info('💳 Số dư Credit khả dụng: 500 Credits');
    }
}
