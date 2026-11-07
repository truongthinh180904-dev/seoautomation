# ROADMAP_P3.md — Đặc Tả Chi Tiết 10 Nhóm Task Giai Đoạn 3
## SEO AUTOMATION & INTELLIGENCE (PHASE 3)

> **Dự án:** Multi-Tenant SaaS Auto-SEO Content Automation  
> **Tech Stack:** Laravel 13 (PHP 8.3) + Next.js 16 (React 19) + Redis + MySQL 8  
> **Bộ điều phối:** QLPK Safe Autonomous Framework (Anti-Fake-Done Gate)  
> **Nguyên tắc cốt lõi:** **TÁI SỬ DỤNG 100% MODULE PHASE 1, 2 VÀ 2.5** — Không viết lại service cũ, không phá vỡ tính cô lập Multi-Tenant, Zero-loss Credit.  

---

## BẢNG 10 NHÓM TASK VÀ LỆNH TEST AUTOMATED

| Đợt | Mã Task | Tên Phân Hệ | Mục Tiêu Kỹ Thuật Trọng Tâm | Ưu Tiên | Lệnh Test Automated |
| :---: | :--- | :--- | :--- | :---: | :--- |
| **Đợt 1** | **P3.1** | Integration Foundation | Cấu hình Google OAuth, `GoogleAccount` Model, `TokenVaultService` mã hóa AES, DTOs, BaseSyncJob. | **P0** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=IntegrationFoundationTest` |
| **Đợt 2** | **P3.2** | Google Search Console | Kết nối GSC Property với Website, đồng bộ Search Analytics theo query/page/date/device/country, phân trang 25k dòng, 0 Credit. | **P0** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=GoogleSearchConsoleTest` |
| **Đợt 3** | **P3.3** | Google Analytics 4 | Kết nối GA4 Property, lưu timezone, đồng bộ sessions, users, engagement, conversions, chuẩn hóa landing page path. | **P0** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=GoogleAnalytics4Test` |
| **Đợt 4** | **P3.4** | SEO Data Warehouse | Schema Time-Series tối ưu với MD5 hash cho URL/query, Composite Unique Keys, Job Queue đồng bộ, Recharts Dashboard. | **P0** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=SeoDataWarehouseTest && cd ../frontend && npm run build` |
| **Đợt 5** | **P3.5** | Rank Tracking (Serper) | Quản lý từ khóa theo dõi, Job cào Top 100 qua Serper.dev API, lưu vết lịch sử theo ngày, tính $\Delta rank$, Dashboard Rank Tracker. | **P0** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=RankTrackingTest && cd ../frontend && npm run build` |
| **Đợt 6** | **P3.6** | SEO Intelligence Engine | Thuật toán Heuristics 0 Credit: Phát hiện tụt hạng, CTR thấp, traffic decay, cannibalization, striking distance, tính Opportunity Score. | **P1** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=SeoIntelligenceTest` |
| **Đợt 7** | **P3.7** | AI Content Refresh | Quy trình làm mới bài viết cũ: Diff Viewer Trước/Sau 2 cột, chấm điểm SEO trước/sau, Cổng duyệt người dùng trước khi cập nhật WordPress. | **P0** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=ContentRefreshOptimizationTest && cd ../frontend && npm run build` |
| **Đợt 8** | **P3.8** | SEO Automation Engine | Lập lịch Scheduler với Distributed Lock `withoutOverlapping`, Bật/Tắt tự động hóa theo website, Cảnh báo in-app notification. | **P1** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=SeoAutomationSchedulerTest` |
| **Đợt 9** | **P3.9** | Chatbot & Approval | Mở rộng Chatbot Phase 2.5 với 4 Tool Adapters (GSC, GA4, Rank, Refresh), hiển thị số liệu SEO và thẻ duyệt cập nhật WordPress trong chat. | **P0** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test --filter=ChatbotSeoAutomationToolTest` |
| **Đợt 10**| **P3.10**| Security, Performance & E2E | Kiểm thử cô lập Tenant 100% (chống IDOR), Token Encryption, Benchmark truy vấn < 100ms, Test E2E khép kín toàn bộ Phase 3. | **P0** | `cd backend && (composer dump-autoload -q \|\| true) && ./vendor/bin/pint --test && php artisan test && cd ../frontend && npm run build` |

---

Chi tiết đầy đủ xem tại [PHASE_3_TASK_BREAKDOWN.md](file:///Users/truongthinh/Documents/SaasAutoSeo/PHASE_3_TASK_BREAKDOWN.md).
