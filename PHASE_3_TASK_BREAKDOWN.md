# PHASE_3_TASK_BREAKDOWN.md — Chi Tiết Kế Hoạch Triển Khai 10 Nhóm Task Phase 3
## AuraSEO – SEO Automation & Intelligence

> **Dự án:** Multi-Tenant SaaS Auto-SEO Content Automation  
> **Tech Stack:** Laravel 13 (PHP 8.3) + Next.js 16 (React 19) + Redis + MySQL 8 (`DBAURASEO`)  
> **Bộ điều phối:** QLPK Safe Autonomous Framework  
> **Nguyên tắc cốt lõi:** **TÁI SỬ DỤNG 100% MODULE PHASE 1, 2 VÀ 2.5** — Tuyệt đối không xóa logic cũ, không vi phạm Multi-Tenancy, không trừ credit sai quy định.  

---

## MỤC LỤC CHI TIẾT
1. [Master Task Summary Table (Bảng Tổng Hợp 10 Task & 97 Subtasks)](#1-master-task-summary-table)
2. [Chi Tiết Đặc Tả Kỹ Thuật 10 Nhóm Task (P3.1 – P3.10)](#2-chi-tiết-đặc-tả-kỹ-thuật-10-nhóm-task)
   - [TASK P3.1: Integration Foundation & Architecture](#task-p31-integration-foundation--architecture)
   - [TASK P3.2: Google OAuth 2.0 & Search Console Integration](#task-p32-google-oauth-20--search-console-integration)
   - [TASK P3.3: Google Analytics 4 Integration](#task-p33-google-analytics-4-integration)
   - [TASK P3.4: SEO Data Warehouse & Analytics Dashboard](#task-p34-seo-data-warehouse--analytics-dashboard)
   - [TASK P3.5: Rank Tracking với Serper.dev](#task-p35-rank-tracking-với-serperdev)
   - [TASK P3.6: SEO Intelligence Engine](#task-p36-seo-intelligence-engine)
   - [TASK P3.7: AI Content Refresh & Optimization](#task-p37-ai-content-refresh--optimization)
   - [TASK P3.8: SEO Automation Engine](#task-p38-seo-automation-engine)
   - [TASK P3.9: Chatbot & Human Approval Integration](#task-p39-chatbot--human-approval-integration)
   - [TASK P3.10: Security, Performance & Final Acceptance](#task-p310-security-performance--final-acceptance)
3. [Quy Tắc Quản Lý Files Bắt Buộc (Allowed vs Forbidden Files)](#3-quy-tắc-quản-lý-files-bắt-buộc)

---

## 1. MASTER TASK SUMMARY TABLE

| Task ID | Tên Nhóm Task & Đầu Việc Cốt Lõi | Dependencies | Allowed Files | Test Suite Chính | Acceptance Criteria | Priority |
| :---: | :--- | :---: | :--- | :--- | :--- | :---: |
| **P3.1** | **Integration Foundation & Architecture**<br>• P3.1.1: Audit 12 service tái sử dụng<br>• P3.1.2: Base Integration Client<br>• P3.1.3: Token Vault mã hóa AES<br>• P3.1.4: Connection quản lý theo Tenant<br>• P3.1.5: Error, Retry & RateLimiter<br>• P3.1.6: Base Sync Job & Idempotency<br>• P3.1.7: Integration Health Status | Không | `backend/config/services.php`<br>`backend/app/Models/GoogleAccount.php`<br>`backend/app/Services/Integrations/*`<br>`backend/database/migrations/*_create_google_accounts_table.php` | `IntegrationFoundationTest.php` | Token được mã hóa AES trong DB; Cô lập Tenant 100%; Secrets không xuất hiện trong log/frontend. | **P0** |
| **P3.2** | **Google OAuth 2.0 & Search Console**<br>• P3.2.1: OAuth Redirect & Callback<br>• P3.2.2: Lưu mã hóa Access/Refresh Token<br>• P3.2.3: Token Auto-refresh ngầm<br>• P3.2.4: Lấy danh sách GSC Properties<br>• P3.2.5: Map Property với Website<br>• P3.2.6: Sync Search Analytics API<br>• P3.2.7: Kích thước: date, query, page, country, device<br>• P3.2.8: Chỉ số: clicks, imp, ctr, pos<br>• P3.2.9: Phân trang 25k & Chống ghi trùng<br>• P3.2.10: UI Quản lý GSC Property | P3.1 | `backend/app/Services/SEO/GSC/*`<br>`backend/app/Models/WebsiteSearchConsole.php`<br>`backend/app/Http/Controllers/Api/V1/Integrations/GscIntegrationController.php`<br>`frontend/components/features/websites/GscConnectModal.tsx` | `GoogleSearchConsoleTest.php` | Kết nối GSC thành công; Lấy danh sách property; Đồng bộ dữ liệu có phân trang; 0 AI Credit; Chống ghi trùng. | **P0** |
| **P3.3** | **Google Analytics 4 Integration**<br>• P3.3.1: GA4 Data API v1beta Client<br>• P3.3.2: Map GA4 Property & Timezone<br>• P3.3.3: Sync Sessions, Active Users<br>• P3.3.4: Sync Engagement Rate & Duration<br>• P3.3.5: Sync Conversions (nếu có)<br>• P3.3.6: Sync landing page, source, device, date<br>• P3.3.7: Chuẩn hóa URL landing page<br>• P3.3.8: Xử lý Quota & Sampling<br>• P3.3.9: UI Quản lý GA4 Property | P3.1 | `backend/app/Services/SEO/GA4/*`<br>`backend/app/Models/WebsiteGoogleAnalytics.php`<br>`backend/app/Http/Controllers/Api/V1/Integrations/Ga4IntegrationController.php`<br>`frontend/components/features/websites/Ga4ConnectModal.tsx` | `GoogleAnalytics4Test.php` | Kết nối GA4 độc lập GSC; Đọc đúng Timezone; Map đúng landing page với bài viết; 0 AI Credit. | **P0** |
| **P3.4** | **SEO Data Warehouse & Analytics Dashboard**<br>• P3.4.1: Schema Time-Series Hash MD5<br>• P3.4.2: Composite Keys & Indexing<br>• P3.4.3: Chuẩn hóa URL & Query Identity<br>• P3.4.4: Xử lý dữ liệu cập nhật bù 3 ngày<br>• P3.4.5: Aggregator theo ngày/tuần/tháng<br>• P3.4.6: API Analytics Performance<br>• P3.4.7: Dashboard Recharts trực quan<br>• P3.4.8: Bộ lọc Date, Page, Query, Device<br>• P3.4.9: So sánh kỳ trước ($\Delta\%$) | P3.2, P3.3 | `backend/app/Models/GscSearchPerformanceDaily.php`<br>`backend/app/Models/Ga4PagePerformanceDaily.php`<br>`backend/app/Jobs/SEO/SyncWebsiteGscDataJob.php`<br>`backend/app/Jobs/SEO/SyncWebsiteGa4DataJob.php`<br>`backend/app/Services/SEO/Analytics/*`<br>`frontend/app/(dashboard)/analytics/page.tsx`<br>`frontend/components/features/analytics/*` | `SeoDataWarehouseTest.php` | Lưu trữ tối ưu; Upsert không sinh bản ghi trùng; Query 100k dòng < 100ms; Biểu đồ Recharts so sánh kỳ mượt mà. | **P0** |
| **P3.5** | **Rank Tracking với Serper.dev**<br>• P3.5.1: Audit Serper.dev Adapter<br>• P3.5.2: Chuẩn hóa Endpoint & Giá API ($0.001)<br>• P3.5.3: Rank Tracking Service<br>• P3.5.4: Quản lý từ khóa theo Website<br>• P3.5.5: Cấu hình GL=vn, HL=vi, Device<br>• P3.5.6: Lưu lịch sử vị trí theo ngày<br>• P3.5.7: So sánh thứ hạng theo kỳ<br>• P3.5.8: Tính biến động $\Delta$ rank<br>• P3.5.9: Giới hạn số lượng theo Plan<br>• P3.5.10: UI Rank Tracker Dashboard | P3.1 | `backend/app/Models/RankTrackedKeyword.php`<br>`backend/app/Models/RankTrackingHistory.php`<br>`backend/app/Services/SEO/RankTracking/*`<br>`backend/app/Jobs/SEO/TrackKeywordRankingJob.php`<br>`frontend/app/(dashboard)/rank-tracker/page.tsx`<br>`frontend/components/features/rank-tracker/*` | `RankTrackingTest.php` | Theo dõi chính xác Top 100 qua Serper; Không tự cào Google; Lưu lịch sử ngày; Dự báo chi phí trước khi chạy. | **P0** |
| **P3.6** | **SEO Intelligence Engine**<br>• P3.6.1: Phát hiện từ khóa tụt hạng (>3 bậc)<br>• P3.6.2: Phát hiện Clicks giảm bất thường<br>• P3.6.3: Phát hiện High Imp / Low CTR<br>• P3.6.4: Phát hiện Traffic Decay (>25%)<br>• P3.6.5: Phát hiện Striking Distance (Top 4-10)<br>• P3.6.6: Phát hiện Keyword Cannibalization<br>• P3.6.7: Phát hiện High Traffic / Low Engagement<br>• P3.6.8: Tính Opportunity Score (0-100)<br>• P3.6.9: Tạo SEO Recommendations<br>• P3.6.10: UI SEO Opportunities Widget | P3.4, P3.5 | `backend/app/Models/SeoIntelligenceInsight.php`<br>`backend/app/Services/SEO/Intelligence/*`<br>`backend/app/Http/Controllers/Api/V1/SEO/SeoIntelligenceController.php`<br>`frontend/components/features/dashboard/SeoInsightsWidget.tsx` | `SeoIntelligenceTest.php` | Phân tích Rule-based 0 Credit; Phân loại đúng 6 dạng cơ hội/bất thường; Không báo động giả do trễ dữ liệu 1 ngày. | **P1** |
| **P3.7** | **AI Content Refresh & Optimization**<br>• P3.7.1: Map Insight với Article hiện có<br>• P3.7.2: Thu thập Context GSC, GA4, Rank<br>• P3.7.3: Nạp BrandKnowledgeBase<br>• P3.7.4: Đối chiếu Top 3 SERP Benchmark<br>• P3.7.5: Phân tích thiếu hụt nội dung cũ<br>• P3.7.6: Đề xuất sửa Title, Meta, H2/H3<br>• P3.7.7: Gợi ý Internal Links bài mới<br>• P3.7.8: Soạn thảo bản mới trong Studio<br>• P3.7.9: Quản lý Version History<br>• P3.7.10: Diff Viewer Trước/Sau 2 cột<br>• P3.7.11: Cổng duyệt đăng WordPress<br>• P3.7.12: Đo lường sau cập nhật (+14 ngày) | P3.4, P3.6 | `backend/app/Services/SEO/Optimization/ContentRefreshService.php`<br>`backend/app/Services/WordPress/WordPressPublishService.php`<br>`backend/app/DTOs/SEO/ContentDiffDTO.php`<br>`frontend/components/features/articles/ContentDiffViewer.tsx`<br>`frontend/components/features/articles/OptimizationRecommendationModal.tsx` | `ContentRefreshOptimizationTest.php` | Không tự ý ghi đè bài cũ; Diff Viewer làm nổi bật thay đổi; Chấm điểm SEO trước/sau tăng; Duyệt mới đăng WP; Credit trừ đúng chuẩn. | **P0** |
| **P3.8** | **SEO Automation Engine**<br>• P3.8.1: Lịch đồng bộ GSC hàng ngày (02:00)<br>• P3.8.2: Lịch đồng bộ GA4 hàng ngày (03:00)<br>• P3.8.3: Lịch Rank Tracking (04:00)<br>• P3.8.4: Lịch Intelligence Anomaly Scan (05:00)<br>• P3.8.5: Tạo Automation Rules theo Website<br>• P3.8.6: Bật/Tắt Automation độc lập<br>• P3.8.7: Notification Center Alert<br>• P3.8.8: Task Logs & Lịch sử tự động<br>• P3.8.9: Retry, Resume an toàn<br>• P3.8.10: UI Quản lý Automation | P3.4, P3.5, P3.6 | `backend/app/Console/Kernel.php`<br>`backend/app/Console/Commands/SeoSyncAllCommand.php`<br>`backend/app/Console/Commands/SeoDetectAnomaliesCommand.php`<br>`backend/app/Events/SeoAnomalyDetectedEvent.php`<br>`backend/app/Listeners/SendSeoAlertNotification.php`<br>`frontend/app/(dashboard)/schedules/page.tsx` | `SeoAutomationSchedulerTest.php` | Scheduler có Distributed Locking `withoutOverlapping`; Không chạy chồng chéo; Gửi thông báo in-app kịp thời; Không tự publish bài. | **P1** |
| **P3.9** | **Chatbot & Human Approval Integration**<br>• P3.9.1: Đăng ký GscToolAdapter<br>• P3.9.2: Đăng ký Ga4ToolAdapter<br>• P3.9.3: Đăng ký RankTrackerToolAdapter<br>• P3.9.4: Đăng ký SeoIntelligenceToolAdapter<br>• P3.9.5: Đăng ký ContentRefreshToolAdapter<br>• P3.9.6: Xử lý hỏi đáp SEO tự nhiên<br>• P3.9.7: Tạo Task từ hội thoại chat<br>• P3.9.8: ChatApprovalCard cập nhật WP<br>• P3.9.9: Streaming tiến độ thời gian thực<br>• P3.9.10: Test E2E Chatbot Workflow | P3.4, P3.5, P3.7 | `backend/app/Services/Chatbot/Tools/SeoToolRegistry.php`<br>`backend/app/Services/Chatbot/AiOrchestratorService.php`<br>`backend/app/Services/Chatbot/Tools/Adapters/*`<br>`frontend/components/features/chatbot/ChatMessageItem.tsx` | `ChatbotSeoAutomationToolTest.php` | Chatbot điều khiển được 4 tính năng mới; Hiển thị thẻ số liệu GSC/Rank; Thẻ xác nhận duyệt cập nhật bài viết an toàn. | **P0** |
| **P3.10**| **Security, Performance & Final Acceptance**<br>• P3.10.1: Test cô lập Tenant 100% (IDOR)<br>• P3.10.2: Audit bảo mật OAuth & Token Vault<br>• P3.10.3: Test API Rate Limits<br>• P3.10.4: Test Credit Zero-Loss Idempotency<br>• P3.10.5: Test Queue Retry & Scheduler Lock<br>• P3.10.6: Test Tính nhất quán dữ liệu ngày<br>• P3.10.7: Benchmark truy vấn Dashboard<br>• P3.10.8: Test E2E Closed-Loop SEO Flywheel<br>• P3.10.9: Regression Test Phase 1, 2, 2.5<br>• P3.10.10: Báo cáo nghiệm thu ký bởi Reviewer | P3.1 $\rightarrow$ P3.9 | `backend/tests/Feature/Phase3SecurityAuditTest.php`<br>`backend/tests/Feature/EndToEndPhase3WorkflowTest.php`<br>`qlpk-autonomous-framework/reports/tasks/PHASE_3_COMPLETION_REPORT.md` | `Phase3SecurityAuditTest.php`<br>`EndToEndPhase3WorkflowTest.php` | 100% Tests Pass; Build Next.js sạch; Không lọt dữ liệu Tenant; Zero Credit Loss; Reviewer cấp PASS. | **P0** |

---

## 2. CHI TIẾT ĐẶC TẢ KỸ THUẬT 10 NHÓM TASK

---

### TASK P3.1: Integration Foundation & Architecture
* **Task ID:** `P3.1`
* **Mục tiêu:** Xây dựng hạ tầng tích hợp bên ngoài dùng chung cho Google OAuth, Search Console, GA4 và Rank Tracking.
* **Các subtasks:**
  - `P3.1.1`: Audit lại 12 service cốt lõi, xác định interface tái sử dụng và kiểm chứng DI container.
  - `P3.1.2`: Xây dựng `AbstractExternalApiClient` chuẩn hóa cấu hình HTTP Timeout (10s), User-Agent, Exponential Backoff và Logging.
  - `P3.1.3`: Xây dựng `TokenVaultService` mã hóa và giải mã tokens bằng AES-256 (`Illuminate\Support\Facades\Crypt`).
  - `P3.1.4`: Tạo Migration & Model `GoogleAccount` liên kết chặt chẽ với `workspace_id` và `user_id`.
  - `P3.1.5`: Cấu hình RateLimiter chuyên dụng cho các API tích hợp (`Limit::perMinute(30)`).
  - `P3.1.6`: Xây dựng `BaseSyncJob` với Idempotency Key và Distributed Lock.
  - `P3.1.7`: Cung cấp API kiểm tra tình trạng kết nối (`IntegrationHealthStatus`).
* **Dependencies:** Không.
* **Allowed Files:**
  - `backend/config/services.php`
  - `backend/database/migrations/*_create_google_accounts_table.php`
  - `backend/app/Models/GoogleAccount.php`
  - `backend/app/Services/Integrations/AbstractExternalApiClient.php`
  - `backend/app/Services/Integrations/TokenVaultService.php`
  - `backend/app/Services/Integrations/GoogleOAuthService.php`
  - `backend/app/DTOs/Integrations/GoogleAuthDTO.php`
  - `backend/app/Jobs/BaseSyncJob.php`
  - `backend/app/Http/Controllers/Api/V1/Integrations/GoogleOAuthController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/IntegrationFoundationTest.php`
  - `frontend/components/features/settings/GoogleIntegrationCard.tsx`
* **Forbidden Files:** Tất cả các controller/service của Phase 1, 2, 2.5 (`ArticleController`, `CreditLedgerService`, `AiOrchestratorService`).
* **Existing Services to Reuse:** `App\Services\Security\AuditLogService` (masking sensitive tokens), `App\Traits\BelongsToWorkspace`.
* **Database Changes:**
  ```sql
  CREATE TABLE `google_accounts` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `user_id` bigint unsigned NOT NULL,
    `google_id` varchar(100) NOT NULL,
    `email` varchar(255) NOT NULL,
    `access_token` text NOT NULL,
    `refresh_token` text DEFAULT NULL,
    `token_expires_at` timestamp NULL DEFAULT NULL,
    `scopes` json NOT NULL,
    `status` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_google_workspace` (`workspace_id`),
    CONSTRAINT `fk_google_acc_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```
* **API Changes:**
  - `GET /api/v1/integrations/google/auth-url`
  - `POST /api/v1/integrations/google/callback`
  - `DELETE /api/v1/integrations/google/{id}`
  - `GET /api/v1/integrations/google/status`
* **Tests:** `IntegrationFoundationTest.php` kiểm tra mã hóa token, lưu trữ theo tenant và xử lý lỗi callback.
* **Acceptance:** Test exit code 0; Token được mã hóa trong DB; Không lộ secrets trong logs.
* **Reviewer Checklist:** Không lưu token dạng plain-text; Model có `BelongsToWorkspace`; Pint PSR-12 pass.

---

### TASK P3.2: Google OAuth 2.0 & Search Console Integration
* **Task ID:** `P3.2`
* **Mục tiêu:** Người dùng kết nối Google Search Console, chọn Property và đồng bộ dữ liệu Search Analytics.
* **Các subtasks:**
  - `P3.2.1`: Xây dựng Google OAuth Authorization Flow với scope `https://www.googleapis.com/auth/webmasters.readonly`.
  - `P3.2.2`: Lưu và mã hóa `access_token` và `refresh_token` vào `google_accounts`.
  - `P3.2.3`: Xây dựng cơ chế tự động làm mới token ngầm khi hết hạn (`GoogleOAuthService::refreshTokenIfNeeded`).
  - `P3.2.4`: Gọi Search Console API `sites.list` để lấy danh sách Properties được cấp quyền.
  - `P3.2.5`: Tạo Migration & Model `WebsiteSearchConsole` liên kết 1 Property với 1 Website trong SaaS.
  - `P3.2.6`: Xây dựng `GoogleSearchConsoleService::querySearchAnalytics` đồng bộ dữ liệu.
  - `P3.2.7`: Hỗ trợ đầy đủ các dimensions: `date`, `query`, `page`, `country`, `device`.
  - `P3.2.8`: Hỗ trợ đầy đủ các metrics: `clicks`, `impressions`, `ctr`, `position`.
  - `P3.2.9`: Xử lý phân trang (batch 25.000 dòng), Rate limit Google API (10 req/s), Retry exponential backoff và chống ghi đè dữ liệu sai lệch.
  - `P3.2.10`: Xây dựng UI Modal kết nối GSC Property tại trang quản trị Website.
* **Dependencies:** P3.1.
* **Allowed Files:**
  - `backend/database/migrations/*_create_website_search_consoles_table.php`
  - `backend/app/Models/WebsiteSearchConsole.php`
  - `backend/app/Services/SEO/GSC/GoogleSearchConsoleService.php`
  - `backend/app/DTOs/SEO/GscSearchMetricDTO.php`
  - `backend/app/Http/Controllers/Api/V1/Integrations/GscIntegrationController.php`
  - `backend/app/Http/Requests/SEO/ConnectGscRequest.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/GoogleSearchConsoleTest.php`
  - `frontend/components/features/websites/GscConnectModal.tsx`
  - `frontend/hooks/useGscIntegration.ts`
* **Forbidden Files:** `CreditLedgerService`, `WordPressPublishService`, `ContentStudioService`.
* **Existing Services to Reuse:** `App\Models\Website`, `App\Traits\BelongsToWorkspace`.
* **Database Changes:**
  ```sql
  CREATE TABLE `website_search_consoles` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `website_id` bigint unsigned NOT NULL,
    `google_account_id` bigint unsigned NOT NULL,
    `site_url` varchar(500) NOT NULL,
    `property_type` enum('url_prefix','domain') NOT NULL DEFAULT 'url_prefix',
    `permission_level` varchar(50) DEFAULT NULL,
    `is_verified` tinyint(1) NOT NULL DEFAULT 1,
    `last_synced_at` timestamp NULL DEFAULT NULL,
    `sync_status` varchar(30) NOT NULL DEFAULT 'idle',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_website_gsc` (`website_id`),
    KEY `idx_gsc_workspace` (`workspace_id`),
    CONSTRAINT `fk_gsc_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```
* **API Changes:**
  - `GET /api/v1/websites/{id}/gsc/properties`
  - `POST /api/v1/websites/{id}/gsc/connect`
  - `POST /api/v1/websites/{id}/gsc/disconnect`
  - `POST /api/v1/websites/{id}/gsc/test`
* **Tests:** `GoogleSearchConsoleTest.php` mock Google API responses, kiểm tra lưu cấu hình, cô lập tenant và test auto-refresh token.
* **Acceptance:** Kết nối thành công GSC Property; Không trừ credit AI; Lọc đúng website của workspace.
* **Reviewer Checklist:** Không hardcode Google Client Secret; Đảm bảo auto-refresh token hoạt động khi token hết hạn; Pint pass.

---

### TASK P3.3: Google Analytics 4 Integration
* **Task ID:** `P3.3`
* **Mục tiêu:** Đồng bộ dữ liệu Google Analytics 4 để phân tích hiệu quả truy cập và hành vi trên từng bài viết.
* **Các subtasks:**
  - `P3.3.1`: Tích hợp GA4 Data API v1beta Client với scope `https://www.googleapis.com/auth/analytics.readonly`.
  - `P3.3.2`: Cho phép người dùng chọn GA4 Property ID và lưu trữ timezone của property.
  - `P3.3.3`: Đồng bộ chỉ số traffic cơ bản: `sessions`, `activeUsers`.
  - `P3.3.4`: Đồng bộ chỉ số tương tác: `engagementRate`, `averageSessionDuration`.
  - `P3.3.5`: Đồng bộ `conversions` khi property đã cấu hình custom event chuyển đổi (không tự suy diễn conversion).
  - `P3.3.6`: Đồng bộ theo dimensions: `date`, `landingPagePlusQueryString`, `sessionSourceMedium`, `deviceCategory`.
  - `P3.3.7`: Thuật toán chuẩn hóa URL: Bỏ query string UTM, trích xuất landing page path và tự động map với `articles.wordpress_post_url` hoặc `articles.slug`.
  - `P3.3.8`: Xử lý hạn mức Quota GA4 Data API (Tokens per project per hour), Sampling flag và xử lý lỗi phân trang.
  - `P3.3.9`: Xây dựng UI Modal kết nối GA4 Property tại trang quản trị Website.
* **Dependencies:** P3.1.
* **Allowed Files:**
  - `backend/database/migrations/*_create_website_google_analytics_table.php`
  - `backend/app/Models/WebsiteGoogleAnalytics.php`
  - `backend/app/Services/SEO/GA4/GoogleAnalyticsService.php`
  - `backend/app/DTOs/SEO/Ga4PerformanceDTO.php`
  - `backend/app/Http/Controllers/Api/V1/Integrations/Ga4IntegrationController.php`
  - `backend/app/Http/Requests/SEO/ConnectGa4Request.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/GoogleAnalytics4Test.php`
  - `frontend/components/features/websites/Ga4ConnectModal.tsx`
  - `frontend/hooks/useGa4Integration.ts`
* **Forbidden Files:** `CreditLedgerService`, `AiOrchestratorService`.
* **Existing Services to Reuse:** `App\Models\Website`, `App\Traits\BelongsToWorkspace`.
* **Database Changes:**
  ```sql
  CREATE TABLE `website_google_analytics` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `website_id` bigint unsigned NOT NULL,
    `google_account_id` bigint unsigned NOT NULL,
    `property_id` varchar(50) NOT NULL,
    `property_name` varchar(255) NOT NULL,
    `time_zone` varchar(100) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
    `last_synced_at` timestamp NULL DEFAULT NULL,
    `sync_status` varchar(30) NOT NULL DEFAULT 'idle',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_website_ga4` (`website_id`),
    KEY `idx_ga4_workspace` (`workspace_id`),
    CONSTRAINT `fk_ga4_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```
* **API Changes:**
  - `GET /api/v1/websites/{id}/ga4/properties`
  - `POST /api/v1/websites/{id}/ga4/connect`
  - `POST /api/v1/websites/{id}/ga4/disconnect`
* **Tests:** `GoogleAnalytics4Test.php` mock GA4 Data API, kiểm tra lấy property, lưu timezone và chuẩn hóa URL landing page.
* **Acceptance:** Kết nối thành công GA4; Đọc đúng Timezone; 0 AI Credit; Dữ liệu phân biệt rõ ràng với GSC.
* **Reviewer Checklist:** Đảm bảo không tự gán bừa conversion; Chuẩn hóa URL landing page không bị vỡ do dấu gạch chéo; Pint pass.

---

### TASK P3.4: SEO Data Warehouse & Analytics Dashboard
* **Task ID:** `P3.4`
* **Mục tiêu:** Lưu trữ lịch sử dữ liệu GSC và GA4 tối ưu hóa cho hàng triệu bản ghi và hiển thị biểu đồ phân tích xu hướng trực quan.
* **Các subtasks:**
  - `P3.4.1`: Thiết kế schema bảng `gsc_search_performance_daily` và `ga4_page_performance_daily` với MD5 Hashing cho URL và Query.
  - `P3.4.2`: Thiết kế Composite Unique Keys và Indexes tối ưu hóa truy vấn theo khoảng ngày và theo trang.
  - `P3.4.3`: Chuẩn hóa URL và Query identity, chống phân mảnh dữ liệu.
  - `P3.4.4`: Cơ chế xử lý dữ liệu cập nhật bù 3 ngày gần nhất từ Google (Backfill & Data Freshness buffer).
  - `P3.4.5`: Xây dựng `SeoAnalyticsAggregatorService` tổng hợp theo ngày, tuần, tháng kèm so sánh kỳ trước ($\Delta\%$).
  - `P3.4.6`: Xây dựng RESTful API Analytics trả về metrics Clicks, Impressions, CTR, Position, Sessions, Engagement.
  - `P3.4.7`: Xây dựng giao diện SEO Performance Dashboard với Recharts (AreaChart, BarChart).
  - `P3.4.8`: Bộ lọc đa năng theo Website, khoảng ngày (7, 28, 90 ngày), Thiết bị (Desktop/Mobile), Quốc gia và Từ khóa.
  - `P3.4.9`: Bảng Top Performing Pages, Declining Pages, Top Queries và Low CTR Queries.
* **Dependencies:** P3.2, P3.3.
* **Allowed Files:**
  - `backend/database/migrations/*_create_gsc_search_performance_daily_table.php`
  - `backend/database/migrations/*_create_ga4_page_performance_daily_table.php`
  - `backend/app/Models/GscSearchPerformanceDaily.php`
  - `backend/app/Models/Ga4PagePerformanceDaily.php`
  - `backend/app/Jobs/SEO/SyncWebsiteGscDataJob.php`
  - `backend/app/Jobs/SEO/SyncWebsiteGa4DataJob.php`
  - `backend/app/Services/SEO/Analytics/SeoAnalyticsAggregatorService.php`
  - `backend/app/Http/Controllers/Api/V1/Analytics/SeoPerformanceController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/SeoDataWarehouseTest.php`
  - `frontend/app/(dashboard)/analytics/page.tsx`
  - `frontend/components/features/analytics/SeoPerformanceOverview.tsx`
  - `frontend/components/features/analytics/TopQueriesTable.tsx`
  - `frontend/components/features/analytics/TopPagesTable.tsx`
  - `frontend/hooks/useSeoPerformance.ts`
* **Forbidden Files:** `CreditLedgerService`, `WordPressPublishService`.
* **Existing Services to Reuse:** `Recharts Components` hiện có trong `frontend/components/features/analytics/*`.
* **Database Changes:**
  ```sql
  CREATE TABLE `gsc_search_performance_daily` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `website_id` bigint unsigned NOT NULL,
    `date` date NOT NULL,
    `query` varchar(255) NOT NULL,
    `page_url` varchar(1024) NOT NULL,
    `page_hash` char(32) NOT NULL,
    `query_hash` char(32) NOT NULL,
    `country` char(3) NOT NULL DEFAULT 'vnm',
    `device` enum('DESKTOP','MOBILE','TABLET') NOT NULL DEFAULT 'DESKTOP',
    `clicks` int unsigned NOT NULL DEFAULT 0,
    `impressions` int unsigned NOT NULL DEFAULT 0,
    `ctr` decimal(6,4) NOT NULL DEFAULT 0.0000,
    `position` decimal(6,2) NOT NULL DEFAULT 0.00,
    `created_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_gsc_daily_metric` (`website_id`, `date`, `page_hash`, `query_hash`, `country`, `device`),
    KEY `idx_gsc_date_lookup` (`website_id`, `date`),
    KEY `idx_gsc_page_lookup` (`website_id`, `page_hash`, `date`),
    KEY `idx_gsc_query_lookup` (`website_id`, `query_hash`, `date`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  CREATE TABLE `ga4_page_performance_daily` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `website_id` bigint unsigned NOT NULL,
    `article_id` bigint unsigned DEFAULT NULL,
    `date` date NOT NULL,
    `landing_page` varchar(1024) NOT NULL,
    `page_hash` char(32) NOT NULL,
    `sessions` int unsigned NOT NULL DEFAULT 0,
    `active_users` int unsigned NOT NULL DEFAULT 0,
    `engagement_rate` decimal(6,4) NOT NULL DEFAULT 0.0000,
    `conversions` int unsigned NOT NULL DEFAULT 0,
    `avg_session_duration` decimal(8,2) NOT NULL DEFAULT 0.00,
    `created_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ga4_daily_metric` (`website_id`, `date`, `page_hash`),
    KEY `idx_ga4_date_lookup` (`website_id`, `date`),
    KEY `idx_ga4_article_lookup` (`article_id`, `date`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```
* **API Changes:**
  - `GET /api/v1/analytics/seo/performance` (Query params: website_id, date_from, date_to, compare=true)
  - `GET /api/v1/analytics/seo/top-queries`
  - `GET /api/v1/analytics/seo/top-pages`
  - `POST /api/v1/analytics/seo/sync-now`
* **Tests:** `SeoDataWarehouseTest.php` kiểm tra phân trang API, cơ chế upsert không sinh bản ghi trùng và tính toán tỷ lệ tăng trưởng ($\Delta\%$).
* **Acceptance:** Upsert không sinh trùng lặp; Truy vấn 100k dòng < 100ms; Dashboard hiển thị biểu đồ đẹp mắt; Build Next.js clean.
* **Reviewer Checklist:** Truy vấn có điều kiện `workspace_id`; Dùng pagination; Không load toàn bộ lịch sử vào RAM.

---

### TASK P3.5: Rank Tracking với Serper.dev
* **Task ID:** `P3.5`
* **Mục tiêu:** Tích hợp Serper.dev API để theo dõi thứ hạng từ khóa mục tiêu theo lịch, vị trí địa lý và thiết bị.
* **Các subtasks:**
  - `P3.5.1`: Audit lại cấu hình Serper.dev sẵn có trong `config/services.php` và `config/ai_costs.php`.
  - `P3.5.2`: Xác minh endpoint `https://google.serper.dev/search`, response format và cấu hình rate-limit.
  - `P3.5.3`: Xây dựng `RankTrackingService` và `SerperRankAdapter`.
  - `P3.5.4`: Tạo Migration & Model `RankTrackedKeyword` quản lý từ khóa theo dõi của từng Website.
  - `P3.5.5`: Hỗ trợ cấu hình quốc gia (`gl=vn`), ngôn ngữ (`hl=vi`) và thiết bị (`device=desktop|mobile`).
  - `P3.5.6`: Tạo Migration & Model `RankTrackingHistory` lưu vị trí Top 100 theo ngày.
  - `P3.5.7`: Cơ chế so sánh thứ hạng kỳ trước (Hôm nay vs Hôm qua, 7 ngày trước, 30 ngày trước).
  - `P3.5.8`: Tính toán biến động thứ hạng ($\Delta rank$: tăng, giảm, giữ nguyên, lọt Top, mất Top).
  - `P3.5.9`: Giới hạn số lượng từ khóa và tần suất tracking theo gói cước (Plan limits) và dự báo chi phí trước khi chạy.
  - `P3.5.10`: Xây dựng giao diện Rank Tracking Dashboard tại `/rank-tracker`.
* **Dependencies:** P3.1.
* **Allowed Files:**
  - `backend/database/migrations/*_create_rank_tracking_tables.php`
  - `backend/app/Models/RankTrackedKeyword.php`
  - `backend/app/Models/RankTrackingHistory.php`
  - `backend/app/Services/SEO/RankTracking/RankTrackingService.php`
  - `backend/app/Services/SEO/RankTracking/SerperRankAdapter.php`
  - `backend/app/Jobs/SEO/TrackKeywordRankingJob.php`
  - `backend/app/Http/Controllers/Api/V1/SEO/RankTrackerController.php`
  - `backend/app/Http/Requests/SEO/StoreTrackedKeywordRequest.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/RankTrackingTest.php`
  - `frontend/app/(dashboard)/rank-tracker/page.tsx`
  - `frontend/components/features/rank-tracker/RankKeywordList.tsx`
  - `frontend/components/features/rank-tracker/RankHistoryChart.tsx`
  - `frontend/components/features/rank-tracker/AddTrackedKeywordModal.tsx`
  - `frontend/hooks/useRankTracker.ts`
* **Forbidden Files:** Tự cào Google SERP bằng Puppeteer/Playwright (Strictly Forbidden).
* **Existing Services to Reuse:** `App\Models\Website`, `App\Traits\BelongsToWorkspace`.
* **Database Changes:**
  ```sql
  CREATE TABLE `rank_tracked_keywords` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `website_id` bigint unsigned NOT NULL,
    `keyword_id` bigint unsigned DEFAULT NULL,
    `keyword` varchar(255) NOT NULL,
    `target_url` varchar(1024) DEFAULT NULL,
    `country` char(3) NOT NULL DEFAULT 'vn',
    `device` enum('desktop','mobile') NOT NULL DEFAULT 'desktop',
    `tracking_frequency` enum('daily','weekly') NOT NULL DEFAULT 'daily',
    `current_position` smallint unsigned DEFAULT NULL,
    `previous_position` smallint unsigned DEFAULT NULL,
    `best_position` smallint unsigned DEFAULT NULL,
    `last_checked_at` timestamp NULL DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tracked_keyword` (`website_id`, `keyword`, `country`, `device`),
    KEY `idx_tracked_workspace` (`workspace_id`),
    KEY `idx_tracked_status` (`website_id`, `is_active`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  CREATE TABLE `rank_tracking_history` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `website_id` bigint unsigned NOT NULL,
    `rank_tracked_keyword_id` bigint unsigned NOT NULL,
    `check_date` date NOT NULL,
    `position` smallint unsigned DEFAULT NULL,
    `found_url` varchar(1024) DEFAULT NULL,
    `serp_features` json DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_rank_check` (`rank_tracked_keyword_id`, `check_date`),
    KEY `idx_rank_lookup` (`website_id`, `check_date`),
    CONSTRAINT `fk_rank_hist_keyword` FOREIGN KEY (`rank_tracked_keyword_id`) REFERENCES `rank_tracked_keywords` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```
* **API Changes:**
  - `GET /api/v1/rank-tracker/keywords`
  - `POST /api/v1/rank-tracker/keywords`
  - `DELETE /api/v1/rank-tracker/keywords/{id}`
  - `GET /api/v1/rank-tracker/keywords/{id}/history`
  - `POST /api/v1/rank-tracker/keywords/{id}/refresh`
* **Tests:** `RankTrackingTest.php` mock kết quả SERP, kiểm tra lưu vết thứ hạng, tính biến động $\Delta rank$ và giới hạn số từ khóa.
* **Acceptance:** Theo dõi thứ hạng chính xác qua Serper; Không tự cào Google; Lưu vết theo ngày không bị trùng; Kiểm soát chi phí chặt chẽ.
* **Reviewer Checklist:** Không lưu kết quả trùng ngày; Kiểm tra quyền hạn theo plan; Pint pass.

---

### TASK P3.6: SEO Intelligence Engine
* **Task ID:** `P3.6`
* **Mục tiêu:** Tự động phân tích dữ liệu tổng hợp từ GSC, GA4 và Rank Tracking để phát hiện 6 dạng bất thường và cơ hội SEO mà không tốn credit AI.
* **Các subtasks:**
  - `P3.6.1`: Phát hiện từ khóa tụt hạng (Ranking Drop: Tụt $>3$ bậc trong 7 ngày gần nhất).
  - `P3.6.2`: Phát hiện Clicks giảm bất thường (Clicks Drop: Giảm $>20\%$ so với tuần trước).
  - `P3.6.3`: Phát hiện CTR thấp (High Impressions / Low CTR: Impressions $>1.000$ nhưng CTR $<2\%$).
  - `P3.6.4`: Phát hiện suy giảm lưu lượng bài viết (Traffic Decay: Giảm clicks liên tục trong 4 tuần).
  - `P3.6.5`: Phát hiện từ khóa có cơ hội bứt phá (Striking Distance: Xếp hạng 4 - 10, impressions cao).
  - `P3.6.6`: Phát hiện ăn thịt từ khóa (Keyword Cannibalization: 2 URL cùng ranking 1 query trong Top 20).
  - `P3.6.7`: Phát hiện trang có traffic nhưng tỷ lệ tương tác thấp (High Traffic / Low Engagement: Sessions cao, engagement rate $<30\%$).
  - `P3.6.8`: Tính toán `Opportunity Score` (0 - 100) dựa trên độ chênh lệch tiềm năng.
  - `P3.6.9`: Tạo bản ghi `SeoIntelligenceInsight` kèm giải thích lý do rõ ràng và hành động đề xuất cụ thể.
  - `P3.6.10`: Xây dựng UI Widget "SEO Insights & Opportunities" trên Dashboard chính.
* **Dependencies:** P3.4, P3.5.
* **Allowed Files:**
  - `backend/database/migrations/*_create_seo_intelligence_insights_table.php`
  - `backend/app/Models/SeoIntelligenceInsight.php`
  - `backend/app/Services/SEO/Intelligence/SeoIntelligenceService.php`
  - `backend/app/Services/SEO/Intelligence/Evaluators/*`
  - `backend/app/Http/Controllers/Api/V1/SEO/SeoIntelligenceController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/SeoIntelligenceTest.php`
  - `frontend/components/features/dashboard/SeoInsightsWidget.tsx`
  - `frontend/components/features/dashboard/InsightCard.tsx`
  - `frontend/hooks/useSeoInsights.ts`
* **Forbidden Files:** `CreditLedgerService` (Scan heuristics là 0 credit, cấm trừ credit ở task này).
* **Existing Services to Reuse:** Bảng `gsc_search_performance_daily`, `ga4_page_performance_daily`, `rank_tracking_history`.
* **Database Changes:**
  ```sql
  CREATE TABLE `seo_intelligence_insights` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `website_id` bigint unsigned NOT NULL,
    `article_id` bigint unsigned DEFAULT NULL,
    `type` enum('ranking_drop','high_imp_low_ctr','traffic_decay','cannibalization','stale_content','striking_distance') NOT NULL,
    `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
    `title` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `metrics_snapshot` json NOT NULL,
    `suggested_action` varchar(100) NOT NULL,
    `status` enum('detected','in_progress','resolved','dismissed') NOT NULL DEFAULT 'detected',
    `resolved_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_insights_tenant` (`workspace_id`, `website_id`, `status`),
    KEY `idx_insights_article` (`article_id`),
    CONSTRAINT `fk_insights_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```
* **API Changes:**
  - `GET /api/v1/seo-insights`
  - `POST /api/v1/seo-insights/{id}/dismiss`
  - `POST /api/v1/seo-insights/{id}/action`
* **Tests:** `SeoIntelligenceTest.php` giả lập dữ liệu tụt hạng/traffic decay, kiểm tra phát hiện chính xác loại anomaly và không sinh cảnh báo trùng lặp.
* **Acceptance:** Phát hiện đúng 6 dạng cơ hội/bất thường; Thuần PHP heuristics 0 credit; Không báo động giả do trễ số liệu.
* **Reviewer Checklist:** Đảm bảo có ngưỡng cấu hình (thresholds); Có giải thích số liệu rõ ràng; Pint pass.

---

### TASK P3.7: AI Content Refresh & Optimization
* **Task ID:** `P3.7`
* **Mục tiêu:** Sử dụng dữ liệu thực tế từ GSC/GA4/Rank Tracking để AI đề xuất cập nhật bài viết cũ, hiển thị Diff Viewer Trước/Sau và yêu cầu duyệt trước khi đăng WordPress.
* **Các subtasks:**
  - `P3.7.1`: Liên kết `SeoIntelligenceInsight` với `Article` mục tiêu.
  - `P3.7.2`: Thu thập bối cảnh từ GSC (truy vấn có impressions cao mà bài chưa tối ưu), GA4 (tỷ lệ thoát), Rank Tracking.
  - `P3.7.3`: Tái sử dụng `BrandKnowledgeService` nạp Brand Persona, Tone, Taboo Words vào prompt.
  - `P3.7.4`: Tái sử dụng `CompetitorAnalysisService` cào Top 3 đối thủ để làm chuẩn đối chiếu (Top 1 Anchor).
  - `P3.7.5`: Phân tích bài viết hiện tại và chỉ ra các đoạn nội dung bị thiếu hụt so với Search Intent mới.
  - `P3.7.6`: Đề xuất cập nhật tiêu đề SEO, Meta Description và bổ sung các đề mục H2/H3 mới.
  - `P3.7.7`: Đề xuất bổ sung liên kết nội bộ trỏ tới bài viết mới qua `InternalLinkingEngine`.
  - `P3.7.8`: Tạo bản thảo tối ưu mới trong `ContentStudioService` (không ghi đè bài viết gốc).
  - `P3.7.9`: Quản lý phiên bản bài viết (`HasVersions`) với ghi chú "AI Content Refresh".
  - `P3.7.10`: Xây dựng component `ContentDiffViewer` hiển thị so sánh Trước và Sau (Side-by-side hoặc Inline Diff).
  - `P3.7.11`: Cổng Human-in-the-Loop: Chỉ cập nhật bài viết lên WordPress khi người dùng bấm "Xác nhận cập nhật".
  - `P3.7.12`: Lưu mốc thời gian `last_optimized_at` và theo dõi biến động hiệu quả sau 14 ngày.
* **Dependencies:** P3.4, P3.6.
* **Allowed Files:**
  - `backend/database/migrations/*_add_optimization_fields_to_articles_table.php`
  - `backend/app/Models/Article.php`
  - `backend/app/Services/SEO/Optimization/ContentRefreshService.php`
  - `backend/app/Services/WordPress/WordPressPublishService.php`
  - `backend/app/DTOs/SEO/ContentDiffDTO.php`
  - `backend/app/Http/Controllers/Api/V1/Article/ContentOptimizationController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/ContentRefreshOptimizationTest.php`
  - `frontend/components/features/articles/ContentDiffViewer.tsx`
  - `frontend/components/features/articles/OptimizationRecommendationModal.tsx`
  - `frontend/hooks/useContentOptimization.ts`
* **Forbidden Files:** Tự ý xuất bản lên WordPress mà không có sự đồng ý của người dùng.
* **Existing Services to Reuse:** `ContentStudioService`, `SeoScoringEngine`, `BrandKnowledgeService`, `CreditLedgerService`, `WordPressPublishService`.
* **Database Changes:**
  ```sql
  ALTER TABLE `articles` 
    ADD COLUMN `last_optimized_at` timestamp NULL DEFAULT NULL AFTER `quality_report`,
    ADD COLUMN `optimization_notes` text DEFAULT NULL AFTER `last_optimized_at`,
    ADD COLUMN `pre_optimization_score` int unsigned DEFAULT NULL AFTER `optimization_notes`;
  ```
* **API Changes:**
  - `POST /api/v1/articles/{id}/optimization-plan` (Trừ 2 credit AI analysis)
  - `GET /api/v1/articles/{id}/diff-preview`
  - `POST /api/v1/articles/{id}/apply-optimization`
  - `POST /api/v1/articles/{id}/push-update-wordpress` (Cần xác nhận)
* **Tests:** `ContentRefreshOptimizationTest.php` kiểm tra sinh đề xuất tối ưu, hiển thị diff, so sánh điểm SEO tăng lên và kiểm tra chỉ cập nhật WP khi được phê duyệt.
* **Acceptance:** Không ghi đè nội dung gốc; Diff viewer làm nổi bật thay đổi; Chấm điểm SEO trước/sau; Bắt buộc người dùng duyệt; Khấu trừ credit đúng quy định.
* **Reviewer Checklist:** Không tự publish; Hàm `updatePost` trong `WordPressPublishService` xử lý lỗi 401/404; Pint pass.

---

### TASK P3.8: SEO Automation Engine
* **Task ID:** `P3.8`
* **Mục tiêu:** Tự động hóa các tác vụ định kỳ của hệ thống với Distributed Locking, quản lý bật/tắt theo Website và gửi thông báo kịp thời.
* **Các subtasks:**
  - `P3.8.1`: Thiết lập Scheduler đồng bộ GSC hàng ngày (02:00 sáng).
  - `P3.8.2`: Thiết lập Scheduler đồng bộ GA4 hàng ngày (03:00 sáng).
  - `P3.8.3`: Thiết lập Scheduler kiểm tra Rank Tracking (04:00 sáng).
  - `P3.8.4`: Thiết lập Scheduler quét SEO Intelligence Heuristics (05:00 sáng).
  - `P3.8.5`: Xây dựng bảng quy tắc tự động hóa (`automation_rules`) cho phép bật/tắt theo Website.
  - `P3.8.6`: Cho phép người dùng cấu hình ngưỡng cảnh báo (Alert Thresholds) cho từng Website.
  - `P3.8.7`: Bắn thông báo in-app và gửi Email/Webhook khi phát hiện cảnh báo nghiêm trọng (Critical Dropping).
  - `P3.8.8`: Lưu trữ nhật ký tự động hóa (`task_logs`) và lịch sử chạy job.
  - `P3.8.9`: Xử lý Retry an toàn, Exponential Backoff và Cancel tác vụ khi bị nghẽn.
  - `P3.8.10`: Xây dựng giao diện Quản lý Tự Động Hóa tại `/settings/schedules`.
* **Dependencies:** P3.4, P3.5, P3.6.
* **Allowed Files:**
  - `backend/database/migrations/*_create_website_automation_rules_table.php`
  - `backend/app/Models/WebsiteAutomationRule.php`
  - `backend/app/Console/Kernel.php`
  - `backend/app/Console/Commands/SeoSyncAllCommand.php`
  - `backend/app/Console/Commands/SeoTrackRankingsCommand.php`
  - `backend/app/Console/Commands/SeoDetectAnomaliesCommand.php`
  - `backend/app/Events/SeoAnomalyDetectedEvent.php`
  - `backend/app/Listeners/SendSeoAlertNotification.php`
  - `backend/app/Services/SEO/Automation/SeoAutomationSchedulerService.php`
  - `backend/app/Http/Controllers/Api/V1/SEO/SeoAutomationController.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/SeoAutomationSchedulerTest.php`
  - `frontend/app/(dashboard)/settings/schedules/page.tsx`
  - `frontend/components/features/settings/AutomationSettingsCard.tsx`
* **Forbidden Files:** Tự động publish bài viết mới mà chưa có sự đồng ý của người dùng.
* **Existing Services to Reuse:** `App\Models\Notification`, `App\Models\Task`, `withoutOverlapping()` trong Laravel Scheduler.
* **Database Changes:**
  ```sql
  CREATE TABLE `website_automation_rules` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `workspace_id` bigint unsigned NOT NULL,
    `website_id` bigint unsigned NOT NULL,
    `sync_gsc_enabled` tinyint(1) NOT NULL DEFAULT 1,
    `sync_ga4_enabled` tinyint(1) NOT NULL DEFAULT 1,
    `rank_tracking_enabled` tinyint(1) NOT NULL DEFAULT 1,
    `anomaly_scan_enabled` tinyint(1) NOT NULL DEFAULT 1,
    `alert_ranking_drop_threshold` smallint unsigned NOT NULL DEFAULT 3,
    `alert_traffic_drop_percentage` smallint unsigned NOT NULL DEFAULT 20,
    `email_notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_website_rules` (`website_id`),
    KEY `idx_rules_workspace` (`workspace_id`),
    CONSTRAINT `fk_rules_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```
* **API Changes:**
  - `GET /api/v1/websites/{id}/automation-rules`
  - `PUT /api/v1/websites/{id}/automation-rules`
* **Tests:** `SeoAutomationSchedulerTest.php` kiểm tra chạy command thành công, có distributed lock không bị chạy trùng và bắn thông báo đúng người nhận.
* **Acceptance:** Command chạy không bị lỗi; Có `withoutOverlapping()`; Người dùng bật/tắt được theo website; Không tự publish bài.
* **Reviewer Checklist:** Kiểm tra khóa lock thời gian thực; Kiểm tra ghi log vào `storage/logs/scheduler.log`; Pint pass.

---

### TASK P3.9: Chatbot & Human Approval Integration
* **Task ID:** `P3.9`
* **Mục tiêu:** Mở rộng Chatbot Phase 2.5 với 4 Tool Adapters mới, cho phép người dùng ra lệnh bằng ngôn ngữ tự nhiên để tra cứu số liệu SEO và duyệt cập nhật nội dung.
* **Các subtasks:**
  - `P3.9.1`: Xây dựng `GscToolAdapter` cho phép Chatbot tra cứu số liệu Search Console ("Tháng này website tăng bao nhiêu clicks?").
  - `P3.9.2`: Xây dựng `Ga4ToolAdapter` cho phép Chatbot tra cứu lượt truy cập và hành vi ("Landing page nào có nhiều người dùng nhất?").
  - `P3.9.3`: Xây dựng `RankTrackerToolAdapter` cho phép Chatbot kiểm tra thứ hạng từ khóa ("Từ khóa 'thiết kế web' đang đứng top mấy?").
  - `P3.9.4`: Xây dựng `SeoIntelligenceToolAdapter` cho phép Chatbot giải thích các cảnh báo bất thường ("Có bài viết nào đang bị tụt traffic không?").
  - `P3.9.5`: Xây dựng `ContentRefreshToolAdapter` cho phép Chatbot khởi tạo kế hoạch làm mới bài viết cũ ("Hãy tối ưu lại bài viết ID 12").
  - `P3.9.6`: Cập nhật System Prompt trong `AiOrchestratorService` nhúng năng lực của các tool mới và hướng dẫn trả lời tự nhiên.
  - `P3.9.7`: Hỗ trợ Chatbot khởi tạo tác vụ ngầm cho các yêu cầu phức tạp (trả về progress widget).
  - `P3.9.8`: Tích hợp `ChatApprovalCard` trong khung chat: Yêu cầu người dùng bấm "Xác nhận cập nhật lên WordPress" kèm thông tin số credit tiêu tốn.
  - `P3.9.9`: Hỗ trợ streaming phản hồi và cập nhật tiến độ theo thời gian thực.
  - `P3.9.10`: Viết test E2E cho luồng hội thoại điều khiển các công cụ SEO Phase 3.
* **Dependencies:** P3.4, P3.5, P3.7.
* **Allowed Files:**
  - `backend/app/Services/Chatbot/Tools/SeoToolRegistry.php`
  - `backend/app/Services/Chatbot/AiOrchestratorService.php`
  - `backend/app/Services/Chatbot/Tools/Adapters/GscToolAdapter.php`
  - `backend/app/Services/Chatbot/Tools/Adapters/Ga4ToolAdapter.php`
  - `backend/app/Services/Chatbot/Tools/Adapters/RankTrackerToolAdapter.php`
  - `backend/app/Services/Chatbot/Tools/Adapters/SeoIntelligenceToolAdapter.php`
  - `backend/app/Services/Chatbot/Tools/Adapters/ContentRefreshToolAdapter.php`
  - `backend/app/Services/Chatbot/ActionApprovalService.php`
  - `backend/tests/Feature/ChatbotSeoAutomationToolTest.php`
  - `frontend/components/features/chatbot/ChatMessageItem.tsx`
  - `frontend/components/features/chatbot/ChatApprovalCard.tsx`
* **Forbidden Files:** Không sửa đổi logic bảng `conversations` hoặc `CreditLedgerService`.
* **Existing Services to Reuse:** `SeoToolRegistry`, `ConversationService`, `ActionApprovalService`, `CreditLedgerService`.
* **Database Changes:** Không (Tái sử dụng bảng `chat_pending_actions` của Phase 2.5).
* **API Changes:** Tái sử dụng các endpoint Chatbot Phase 2.5 (`/api/v1/conversations/{uuid}/messages`, `/api/v1/chatbot/actions/{uuid}/approve`).
* **Tests:** `ChatbotSeoAutomationToolTest.php` kiểm tra gọi tool GSC/Rank qua chat và kích hoạt thẻ xác nhận duyệt cập nhật bài viết.
* **Acceptance:** Tái sử dụng `SeoToolRegistry`; Chatbot trả lời thông số SEO chính xác; Thẻ duyệt cập nhật hoạt động an toàn; Không vượt quyền người dùng.
* **Reviewer Checklist:** Kiểm tra thẩm quyền tenant trong từng Adapter; Không để AI tự ý duyệt hành động; Pint pass.

---

### TASK P3.10: Security, Performance & Final Acceptance
* **Task ID:** `P3.10`
* **Mục tiêu:** Kiểm toán bảo mật toàn diện, kiểm thử hiệu năng truy vấn dữ liệu lớn và chạy bộ test E2E nghiệm thu khép kín toàn bộ Phase 3.
* **Các subtasks:**
  - `P3.10.1`: Chạy bộ kiểm thử cô lập Multi-Tenant 100% (Workspace 1 tuyệt đối không thể đọc dữ liệu GSC/GA4/Rank của Workspace 2).
  - `P3.10.2`: Kiểm toán bảo mật Token Vault: Xác minh 100% token được mã hóa trong cơ sở dữ liệu và không bị lộ qua API hay logs.
  - `P3.10.3`: Kiểm thử API Rate Limiting: Đảm bảo chống tấn công DoS và lạm dụng API.
  - `P3.10.4`: Kiểm thử Credit Idempotency & Zero-Loss: Chạy retry kịch bản lỗi, xác thực không bị trừ trùng lặp và hoàn tiền 100%.
  - `P3.10.5`: Kiểm thử Queue Retry & Scheduler Lock: Mô phỏng worker chết giữa chừng và scheduler chạy đồng thời.
  - `P3.10.6`: Kiểm thử tính nhất quán dữ liệu ngày (Data Integrity): Không có ngày bị trùng lặp, hash không bị va chạm.
  - `P3.10.7`: Benchmark hiệu năng truy vấn Dashboard: Đo đạc thời gian truy vấn 100.000 dòng dữ liệu dưới 100ms.
  - `P3.10.8`: Chạy bài kiểm thử E2E khép kín: Kết nối Google $\rightarrow$ Đồng bộ GSC/GA4 $\rightarrow$ Cào Rank $\rightarrow$ Phát hiện Bất thường $\rightarrow$ Tối ưu bài viết $\rightarrow$ Duyệt đăng WordPress $\rightarrow$ Kiểm toán Credit khớp 100%.
  - `P3.10.9`: Chạy toàn bộ Regression Test Suite của Phase 1, Phase 2 và Phase 2.5 đảm bảo không có tính năng cũ nào bị hỏng.
  - `P3.10.10`: Lập báo cáo nghiệm thu tổng thể `PHASE_3_COMPLETION_REPORT.md` trình Reviewer Agent ký xác nhận PASS.
* **Dependencies:** P3.1 đến P3.9.
* **Allowed Files:**
  - `backend/tests/Feature/Phase3SecurityAuditTest.php`
  - `backend/tests/Feature/EndToEndPhase3WorkflowTest.php`
  - `qlpk-autonomous-framework/reports/tasks/PHASE_3_COMPLETION_REPORT.md`
  - `qlpk-autonomous-framework/PROGRESS_P3.md`
* **Forbidden Files:** Không sửa đổi logic nghiệp vụ trong task này (chỉ viết test và báo cáo).
* **Existing Services to Reuse:** Toàn bộ test suite và service của hệ thống.
* **Database Changes:** Không.
* **API Changes:** Không.
* **Tests:** `Phase3SecurityAuditTest.php` và `EndToEndPhase3WorkflowTest.php`.
* **Acceptance:** Toàn bộ test suite Laravel (`php artisan test`) pass 100% (`exit code = 0`); Build Next.js clean; Reviewer Agent cấp đánh giá PASS chính thức.
* **Reviewer Checklist:** Kiểm tra báo cáo test thực tế; Đối chiếu Git diff; Xác nhận đầy đủ 10 task đạt chuẩn.

---

## 3. QUY TẮC QUẢN LÝ FILES BẮT BUỘC

1. **Nguyên tắc Allowed Files:** Mỗi task khi thực thi chỉ được phép tạo mới hoặc chỉnh sửa các tệp nằm trong danh sách `Allowed Files` tương ứng.
2. **Quy tắc Forbidden Files:**
   - Cấm xóa hoặc thay đổi signature các method đã ổn định của Phase 1, Phase 2 và Phase 2.5.
   - Cấm sửa đổi file `CreditLedgerService.php` ngoại trừ việc gọi qua interface sẵn có.
   - Cấm quét hoặc sửa các thư mục hệ thống: `node_modules`, `vendor`, `.next`, `dist`, `build`, `.git`.
   - Cấm chạy migration `drop` bảng dữ liệu cũ.

---
**Tài liệu được lập bởi:** Senior Software Architect  
**Trạng thái:** Sẵn sàng cho quá trình triển khai tuần tự khi được phê duyệt.
