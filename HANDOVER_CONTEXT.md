# TÀI LIỆU BÀN GIAO NGỮ CẢNH DỰ ÁN (PROJECT CONTEXT HANDOVER)
## HỆ THỐNG SAAS VIẾT BÀI SEO TỰ ĐỘNG (AURA SEO)
### BỘ ĐIỀU PHỐI ĐA AGENT: QLPK SAFE AUTONOMOUS FRAMEWORK

> **Mục đích:** Tài liệu này ghi lại trọn vẹn 100% ngữ cảnh dự án, kiến trúc hệ thống, trạng thái thực tế của tất cả các giai đoạn (Phase 1, 2, 2.5, 3), các quy tắc kỹ thuật bất biến và cách thức vận hành bộ điều phối tự hành ngầm (`run-agy.sh`).  
> **Dành cho:** Bất kỳ phiên làm việc nào của Antigravity CLI (AGY) hoặc AI Agent mới khi đăng nhập vào dự án đều có thể đọc file này và nắm bắt đầy đủ ngữ cảnh ngay lập tức mà không bị mất dấu vết.  
> **Cập nhật lần cuối:** 06/11/2026 (Sau khi hoàn tất quy hoạch Phase 3)  
> **Nhánh Git làm việc chính:** `dev` (`https://github.com/truongthinh180904-dev/seoautomation.git`)  

---

## 1. TỔNG QUAN DỰ ÁN & TECH STACK

**AuraSEO** là nền tảng SaaS Multi-Tenant tự động hóa sản xuất nội dung chuẩn SEO, nghiên cứu từ khóa, cào và giải mã đối thủ, tối ưu hóa bài viết và tự động hóa toàn diện quy trình SEO thông qua giao diện Chatbot AI tương tác thông minh.

* **Backend:**
  * **Framework:** Laravel 13 (PHP 8.3).
  * **Database:** MySQL 8 (`DBAURASEO`) — quản lý qua Eloquent Models & Migrations.
  * **Cache & Queues:** Redis, Laravel Horizon.
  * **Authentication:** Laravel Sanctum (Token-based API authentication).
  * **Multi-Tenancy:** Trait `App\Traits\BelongsToWorkspace` gán Global Scope theo `workspace_id` của tài khoản đăng nhập.
  * **Financial Ledger:** `App\Services\Billing\CreditLedgerService` tuân thủ nguyên tắc ngân hàng: **Reserve $\rightarrow$ Commit $\rightarrow$ Release** kèm Idempotency Key, loại trừ 100% rủi ro mất credit oan hoặc trừ trùng lặp.
  * **WordPress Integration:** `App\Services\WordPress\WordPressPublishService` đẩy bài trực tiếp qua WordPress REST API với Application Password mã hóa AES (`'app_password' => 'encrypted'`).
* **Frontend:**
  * **Framework:** Next.js 16 (App Router), React 19, TypeScript strict (no `any`).
  * **Styling & UI:** Tailwind CSS v4, shadcn/ui, Radix UI, Lucide Icons.
  * **Data Fetching:** `@tanstack/react-query: ^5.100.9` (caching & revalidation).
  * **State Management:** Zustand (`useAuthStore`).
  * **Rich Text Editor:** Lexical Editor tại `/content-studio`.
  * **Charts:** `recharts: ^3.8.1` tại `/analytics`.
* **Khung điều phối tự hành độc lập:** `qlpk-autonomous-framework/` (Shell Bash + Multi-Model Routing).

---

## 2. HIỆN TRẠNG TIẾN ĐỘ TẤT CẢ CÁC GIAI ĐOẠN

```text
========================================================================================
                              PROJECT ROADMAP PROGRESS MATRIX
========================================================================================
  PHASE 1:   SaaS Foundation & Billing                 [ 100% DONE ]  8/8 Tasks PASS
  PHASE 2:   Advanced SEO AI & Content Studio          [ 100% DONE ]  9/9 Tasks PASS
  PHASE 2.5: AI SEO Chatbot & Conversational Vision    [ 100% DONE ]  9/9 Tasks PASS
  PHASE 3:   SEO Automation & Intelligence (GSC/GA4)   [ SẴN SÀNG ]   10/10 Tasks Ready
========================================================================================
```

### 2.1. Phase 1 — SaaS Foundation & Core Architecture (HOÀN TẤT 100%)
- **TASK-01 $\rightarrow$ TASK-08:** Tạo Database schema, Multi-Tenant isolation (`workspaces`, `websites`, `users`), Authentication Sanctum, Sổ cái Credit Ledger, Cổng nạp tiền Payment Orders (VietQR / Chuyển khoản ngân hàng), Queue Pipeline, Dashboard Next.js 16 và E2E hardening.
- Toàn bộ test suite đạt `exit code = 0`, đã push lên nhánh `dev`.

### 2.2. Phase 2 — Advanced SEO AI & Content Intelligence (HOÀN TẤT 100%)
- **P2.1 $\rightarrow$ P2.9:** Nghiên cứu từ khóa (`KeywordResearchService`), Phân tích SERP Top 3 Benchmark lấy Top 1 làm mỏ neo (`CompetitorAnalysisService`), Topic Clusters & Content Gaps, Brand Knowledge Base (`BrandKnowledgeService`), AI Content Studio với Lexical Editor, Chấm điểm SEO 100 điểm thuần PHP (`SeoScoringEngine`), Đồ thị liên kết nội bộ (`InternalLinkingEngine`), Sản xuất bài hàng loạt (`BulkGenerationService`), Xuất bản WordPress.
- Đã nghiệm thu độc lập và đẩy lên GitHub (commits `c6d2ea0` đến `c9c38e5`).

### 2.3. Phase 2.5 — AI SEO Chatbot & Conversational Automation (HOÀN TẤT 100%)
- **P2.5.1 $\rightarrow$ P2.5.9:** Xây dựng con Chatbot **Aura SEO Assistant** tại `/chatbot` làm trung tâm điều khiển hội thoại cho toàn bộ SaaS:
  1. *Giao diện Chatbot & Ô kéo thả Upload ảnh:* Hỗ trợ xem trước ảnh, chat markdown phong phú, chia 3 cột responsive.
  2. *Conversation Engine:* Bảng `conversations`, `conversation_messages`, quản lý bộ nhớ phiên theo `workspace_id`.
  3. *AI Orchestrator:* Phân tích ý định (Intent), lập kế hoạch nhiều bước (Execution Plan), nạp bối cảnh Brand Voice.
  4. *Tool Calling 10 Công Cụ SEO Sẵn Có:* Tích hợp Top 3 SERP Benchmark và kho ảnh thương mại miễn phí bản quyền (Pexels / Unsplash).
  5. *Chiến Lược Thác Nước Xử Lý Ảnh 3 Tầng Chuẩn SEO:*
     - **Ưu tiên 1 (Cốt lõi):** AI Gemini Vision đọc hiểu nội dung ảnh người dùng gửi, tự động định vị và chèn vào đúng thẻ H2/H3 thích hợp nhất trong bài viết kèm ALT chuẩn SEO.
     - **Ưu tiên 2:** Tự động tìm kiếm ảnh stock không bản quyền (Pexels/Unsplash).
     - **Ưu tiên 3:** AI Gemini (Imagen 3) render ảnh chuẩn ngữ cảnh khi không tìm thấy ảnh stock.
     - *100% hình ảnh được tự động nén nhỏ WebP < 150KB.*
  6. *Task Progress Streaming:* Quét tiến độ tác vụ ngầm từ bảng `tasks` (0% $\rightarrow$ 100%).
  7. *Approval Gate (Human-in-the-Loop):* Bảng `chat_pending_actions`, thẻ `ChatApprovalCard` bắt buộc người dùng bấm xác nhận trước khi đăng WordPress hoặc chạy Bulk.
  8. *Credit Ledger Bridge:* Quy trình Reserve $\rightarrow$ Commit $\rightarrow$ Release, Zero Loss Guarantee.
  9. *Security Audit & E2E:* Vượt qua 100% kiểm tra cô lập Tenant (chống IDOR), chặn Prompt Injection.
- Toàn bộ commit đã được push lên `origin/dev` (commit `d175852` đến `847204f`).

### 2.4. Phase 3 — SEO Automation & Intelligence (ĐÃ QUY HOẠCH HOÀN TẤT, SẴN SÀNG CHẠY NGẦM)
- Nâng cấp AuraSEO thành hệ thống tự động hóa khép kín:
  - **P3.1:** Integration Foundation & Google OAuth Token Vault (mã hóa AES-256).
  - **P3.2:** Google Search Console API (đồng bộ Clicks, Imp, CTR, Pos theo query/page/date/device, **0 Credit**).
  - **P3.3:** Google Analytics 4 API (đồng bộ Sessions, Users, Engagement, map URL bài viết, **0 Credit**).
  - **P3.4:** SEO Data Warehouse & Performance Dashboard (Lưu trữ Time-Series tối ưu hash MD5, Recharts so sánh kỳ trước).
  - **P3.5:** Rank Tracking với Serper.dev API (Cào Top 100, lưu vết lịch sử theo ngày, dự báo chi phí).
  - **P3.6:** SEO Intelligence Heuristic Engine (**0 Credit**): Bắt 6 dạng bất thường (Tụt hạng, CTR thấp, traffic decay, cannibalization, striking distance).
  - **P3.7:** AI Content Refresh: Quy trình làm mới bài viết cũ, Diff Viewer Trước/Sau 2 cột, chấm điểm SEO, **người dùng duyệt mới đăng WordPress**.
  - **P3.8:** SEO Automation Scheduler: Chạy định kỳ với Distributed Lock `withoutOverlapping`, cảnh báo in-app.
  - **P3.9:** Chatbot Integration: 4 Tool Adapters mới (GSC, GA4, Rank, Refresh) và Thẻ duyệt cập nhật bài trong chat.
  - **P3.10:** Security Audit, Performance Benchmark và Test E2E khép kín toàn hệ thống.

---

## 3. CƠ CHẾ ĐIỀU PHỐI ĐA MODEL & CHẠY NGẦM KHÔNG TẮT MÁY

Hệ thống tự hành được điều phối độc lập bởi script [`qlpk-autonomous-framework/run-agy.sh`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/run-agy.sh):

### 3.1. Phân Chia Vai Trò Model (Model Routing Chuẩn):
| Vai Trò | Model Thực Thi | Mode CLI | Nhiệm Vụ |
| :--- | :--- | :---: | :--- |
| **PLANNER** | `gemini-4-argon-medium` | `plan` (Chỉ đọc) | Chia nhỏ task nguyên tử, xác định dependencies và `allowed_files`. |
| **CODER** | `gemini-3.8-flash-high` | `accept-edits` (Ghi file) | Chỉ được sửa các file trong `allowed_files`. Cấm gọi shell trực tiếp. |
| **TESTER** | **Shell Runner Bash thật** | Bash Native Sandbox | Tự động chạy `./vendor/bin/pint --test`, `php artisan test`, `npm run build`. Đòi hỏi `exit_code == 0`. |
| **REVIEWER** | `gemini-4-argon-medium` | `plan` (Chỉ đọc) | Đọc `git diff` thật và file test report. Kiểm duyệt **14 tiêu chí Anti-Fake-Done**. Chỉ cấp `PASS` khi thỏa mãn 100%. |

### 3.2. Tính Năng Chạy Ngầm Không Tắt Máy (Anti-Sleep / Caffeinate):
- Tích hợp lệnh `caffeinate -dimsu -w $$ &` của macOS: **Ngăn chặn tuyệt đối việc sleep máy, tắt màn hình hay ngắt ổ đĩa** khi bạn gập máy Mac chạy xuyên đêm.
- Tự động ngắt khi session hoàn thành.
- Cảm biến kiểm tra tải CPU (`<= 4.0/core`) và RAM trống (`>= 10%`), tự động nghỉ 5-60s nếu máy bị nóng/quá tải để bảo vệ phần cứng.

### 3.3. Tự Động Commit & Push Lên Git Với Tên Task Rõ Ràng:
- Mỗi khi một task vượt qua cổng **Anti-Fake-Done Gate** (`exit_code == 0` VÀ Reviewer `PASS`), hệ thống tự động commit và push lên nhánh `origin/dev`.
- **Định dạng Commit Message mới đã nâng cấp:**  
  `feat({TASK_ID}): {TÊN_TASK_CHI_TIẾT} [{RUN_ID}]`  
  *Ví dụ:*  
  `feat(P3.1): Integration Foundation & Architecture [RUN_20261106_153000_P3.1_R1]`  
  `feat(P3.2): Google OAuth 2.0 & Search Console Integration [RUN_20261106_154500_P3.2_R1]`  
  $\rightarrow$ Giúp lịch sử Git trên GitHub hiển thị đẹp mắt, rõ ràng và cực kỳ dễ theo dõi!

---

## 4. HƯỚNG DẪN CÁC LỆNH CHẠY HỆ THỐNG TRONG PHIÊN LÀM VIỆC MỚI

Khi bạn mở một terminal mới hoặc khởi động phiên AGY mới, bạn có thể điều khiển toàn bộ hệ thống bằng các lệnh đơn giản sau:

### 1. Khởi chạy toàn bộ Phase 3 ngầm:
```bash
./qlpk-autonomous-framework/run-agy.sh run PHASE3
```
*(Hệ thống sẽ tự động bật caffeinate chống ngủ máy, duyệt tuần tự từ P3.1 đến P3.10, tự test, tự review và tự push lên GitHub).*

### 2. Khởi chạy 1 task cụ thể (Ví dụ P3.1):
```bash
./qlpk-autonomous-framework/run-agy.sh run P3.1
```

### 3. Kiểm tra trạng thái tiến trình đang chạy ngầm:
```bash
./qlpk-autonomous-framework/run-agy.sh status
```

### 4. Dừng tiến trình tự hành an toàn:
```bash
./qlpk-autonomous-framework/run-agy.sh stop
```
*(Hệ thống sẽ hoàn thành nốt công đoạn hiện tại và dừng an toàn tại biên giới task tiếp theo).*

---

## 5. DANH MỤC CÁC TÀI LIỆU KỸ THUẬT QUAN TRỌNG

Mọi thông tin chi tiết về từng file code, database schema và chính sách đều được lưu giữ tại:

| Tên Tài Liệu | Đường Dẫn Tuyệt Đối | Mục Đích Sử Dụng |
| :--- | :--- | :--- |
| **Báo cáo kiểm toán Phase 3** | [`PRE_PHASE_3_AUDIT_REPORT.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/PRE_PHASE_3_AUDIT_REPORT.md) | Toàn bộ bằng chứng khảo sát mã nguồn và 12 service tái sử dụng. |
| **Lộ trình Master Phase 3** | [`PHASE_3_ROADMAP.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/PHASE_3_ROADMAP.md) | Tổng quan 10 nhóm task và 5 execution gates. |
| **Đặc tả 97 Subtasks** | [`PHASE_3_TASK_BREAKDOWN.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/PHASE_3_TASK_BREAKDOWN.md) | Chi tiết từng subtask, `allowed_files`, DB changes, API contracts. |
| **Sơ đồ phụ thuộc & Gates** | [`PHASE_3_DEPENDENCY_GRAPH.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/PHASE_3_DEPENDENCY_GRAPH.md) | Sơ đồ Mermaid, đường găng và cơ hội song song hóa. |
| **Chiến lược kiểm thử** | [`PHASE_3_TEST_STRATEGY.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/PHASE_3_TEST_STRATEGY.md) | Ma trận phân tầng test, code mẫu mocking Http::fake. |
| **Chính sách Credit & Chi phí** | [`PHASE_3_COST_AND_CREDIT_POLICY.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/PHASE_3_COST_AND_CREDIT_POLICY.md) | Quy tắc 0 credit GSC/GA4, Reserve-Commit-Release. |
| **Sổ đăng ký rủi ro** | [`PHASE_3_RISK_REGISTER.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/PHASE_3_RISK_REGISTER.md) | Ma trận rủi ro bảo mật (Token AES, IDOR, SSRF, Rate limit). |
| **Bảng tiến độ Framework** | [`qlpk-autonomous-framework/PROGRESS_P3.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/PROGRESS_P3.md) | Bảng theo dõi trạng thái realtime của runner. |
| **Đặc tả Framework** | [`qlpk-autonomous-framework/ROADMAP_P3.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/ROADMAP_P3.md) | Đặc tả 10 task cấp cho Coder/Reviewer đọc. |

---
**Tài liệu được bảo trì bởi:** Senior Software Architect & Autonomous Framework Lead  
**Quy ước:** Bất kỳ phiên làm việc nào của AI Agent khi bắt đầu đều phải đọc tệp này để có đầy đủ 100% ngữ cảnh dự án.
