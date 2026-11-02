# ROADMAP.md — Đặc Tả Chi Tiết 8 Nhóm Task Giai Đoạn 1 (Phase 1)

> **Dự án:** SaaS Viết Bài SEO Tự Động (Multi-Tenant SaaS)  
> **Tech Stack:** Laravel 12/13 (PHP 8.3) + Next.js 16 (React 19, Tailwind CSS v4)  
> **Framework điều phối:** QLPK Multi-Agent Safe Autonomous Framework  
> **Nguyên tắc:** Làm tuần tự từng đợt, không nhảy cóc, nghiệm thu Anti-Fake-Done từng task.

---

## Bảng Tổng Hợp Thứ Tự Triển Khai (Phụ Thuộc & Cổng Nghiệm Thu)

```text
[Đợt 1: TASK-01 (Audit)]
        │
        ▼
[Đợt 2: TASK-02 (Database & Migrations)]
        │
        ▼
[Đợt 3: TASK-03 (Auth, Workspaces, Tenant Security)]
        │
        ▼
[Đợt 4: TASK-04 (Subscription Plans & Monthly Credit Ledger)]
        │
        ▼
[Đợt 5: TASK-05 (Manual Payment & Admin Operations)]
        │
        ▼
[Đợt 6: TASK-06 (Core AI Pipeline & Queue Engine)]
        │
        ▼
[Đợt 7: TASK-07 (Customer & Admin SaaS Dashboard)]
        │
        ▼
[Đợt 8: TASK-08 (Automated Tests, Security Audit & Reviewer Sign-off)]
```

---

## CHI TIẾT 8 NHÓM TASK GIAI ĐOẠN 1

### [NHÓM 1] TASK-01: Audit Code & Database Hiện Tại
* **Ưu tiên:** P0 — Bắt buộc hoàn thành đầu tiên.
* **Mục tiêu:** Hiểu chính xác 100% cấu trúc thực tế của codebase, schema DB, các quan hệ, model, controller, service, queue, API và thư viện UI trước khi sửa code hoặc tạo schema mới.
* **Công việc chi tiết:**
  - `TASK-01.1`: Kiểm tra cấu trúc dự án Laravel (`backend/`) và Next.js (`frontend/`).
  - `TASK-01.2`: Kiểm tra migrations, models, relationships, seeders và factories của Laravel.
  - `TASK-01.3`: Kiểm tra database hiện tại, các bảng, khóa ngoại, indexes và dữ liệu liên quan.
  - `TASK-01.4`: Kiểm tra Controllers, Services, Repositories, Middleware, Policies và Jobs.
  - `TASK-01.5`: Kiểm tra các chức năng AI, viết bài, SEO, nghiên cứu đối thủ, WordPress và quản lý chiến dịch.
  - `TASK-01.6`: Kiểm tra giao diện Next.js, các API đang kết nối và thư viện UI hiện có.
  - `TASK-01.7`: Đối chiếu từng chức năng theo ba trạng thái: *Hoạt động tốt*, *Cần sửa*, *Chưa có*.
  - `TASK-01.8`: Xác định chức năng nào nên giữ lại, tái sử dụng hoặc thay thế.
* **Phụ thuộc:** Không.
* **Tệp cho phép tác động (allowed_files):**
  - `PHASE_1_AUDIT.md` (Tạo mới ở thư mục gốc hoặc trong framework)
  - `qlpk-autonomous-framework/reports/tasks/TASK-01-audit.md`
  - `qlpk-autonomous-framework/PROGRESS.md`
* **Lệnh kiểm thử:** Kiểm tra cú pháp markdown, kiểm chứng file tồn tại.
* **Đầu ra bắt buộc:** Tài liệu `PHASE_1_AUDIT.md` gồm 7 mục:
  1. Kiến trúc hệ thống hiện tại.
  2. Danh sách bảng database và quan hệ.
  3. Danh sách chức năng đã có.
  4. Danh sách lỗi và rủi ro bảo mật.
  5. Đề xuất cấu trúc database mới.
  6. Danh sách file cần sửa, tạo mới hoặc giữ nguyên.
  7. Danh sách migration cần thực hiện và ảnh hưởng dự kiến.
* **Điều kiện nghiệm thu:** Không được tự ý xóa bảng, reset database hoặc viết lại những module đang hoạt động tốt khi chưa hoàn thành báo cáo audit.

---

### [NHÓM 2] TASK-02: Thiết Kế & Khởi Tạo Database SaaS Mới
* **Ưu tiên:** P0.
* **Mục tiêu:** Xây dựng database phù hợp với nền tảng SaaS, hỗ trợ nhiều khách hàng (Workspaces), nhiều website và quản lý credit theo tháng; bảo tồn các bảng nghiệp vụ hiện có (`articles`, `campaigns`, `keywords`, `wordpress_sites`).
* **Công việc chi tiết:**
  - `TASK-02.1`: Chuẩn bị môi trường: kiểm tra PHP, Laravel, cấu hình kết nối database qua `.env`, xác nhận kết nối `DBAURASEO`.
  - `TASK-02.2`: Thiết kế các nhóm bảng mới / chuẩn hóa bảng cũ:
    - *Người dùng & Tổ chức:* `users`, `workspaces`, `workspace_members`, `websites`.
    - *Gói & Đăng ký:* `plans`, `subscriptions`.
    - *Thanh toán thủ công:* `payment_orders`, `payment_transactions`.
    - *Ví Credit & Lịch sử:* `credit_accounts`, `credit_transactions`, `credit_grants`.
    - *Sử dụng & Chi phí:* `usage_records`, `ai_usage_logs`.
    - *Tác vụ & Nhật ký:* `tasks`, `audit_logs`.
  - `TASK-02.3`: Tạo và kiểm thử migrations theo thứ tự phụ thuộc (khóa ngoại, indexes, unique constraints), kiểm thử rollback sạch.
* **Phụ thuộc:** TASK-01.
* **Tệp cho phép tác động (allowed_files):**
  - `backend/composer.json`
  - `backend/.env.example`
  - `backend/config/*.php`
  - `backend/database/migrations/*.php`
  - `backend/database/seeders/*.php`
  - `backend/database/factories/*.php`
  - `backend/app/Models/*.php`
  - `backend/app/Enums/*.php`
  - `backend/tests/Pest.php`
  - `backend/tests/Feature/MigrationSchemaTest.php`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=MigrationSchemaTest`
* **Điều kiện nghiệm thu:** Database mới khởi tạo theo migration sạch, giữ lại dữ liệu và quan hệ của các chức năng cũ không bị vỡ.

---

### [NHÓM 3] TASK-03: Hoàn Thiện Tài Khoản, Workspace & Bảo Mật
* **Ưu tiên:** P0.
* **Mục tiêu:** Thiết lập ranh giới cách ly dữ liệu Multi-Tenant tuyệt đối giữa các Workspace, bảo vệ API, token và WordPress credentials.
* **Công việc chi tiết:**
  - `TASK-03.1`: Hoàn thiện đăng ký, đăng nhập, đăng xuất và khôi phục mật khẩu.
  - `TASK-03.2`: Phân biệt quyền Admin và Customer (Spatie / Policy).
  - `TASK-03.3`: Mỗi khách hàng có workspace riêng (Auto-provision khi đăng ký).
  - `TASK-03.4`: Hỗ trợ một workspace quản lý nhiều website.
  - `TASK-03.5`: Kiểm tra quyền truy cập API dựa trên user, workspace và website.
  - `TASK-03.6`: Rà soát Sanctum token expiry (không để null).
  - `TASK-03.7`: Bảo vệ token, API keys và Application Password WordPress (mã hóa DB).
  - `TASK-03.8`: Thêm giới hạn đăng nhập (Rate limiting) chống brute-force.
  - `TASK-03.9`: Kiểm tra và chặn IDOR (thay ID trên URL/API).
  - `TASK-03.10`: Bổ sung audit log cho thao tác nhạy cảm.
* **Phụ thuộc:** TASK-02.
* **Tệp cho phép tác động (allowed_files):**
  - `backend/bootstrap/app.php`
  - `backend/config/sanctum.php`
  - `backend/config/cors.php`
  - `backend/routes/api.php`
  - `backend/app/Http/Controllers/Api/V1/**/*.php`
  - `backend/app/Http/Middleware/*.php`
  - `backend/app/Http/Requests/**/*.php`
  - `backend/app/Http/Resources/*.php`
  - `backend/app/Repositories/**/*.php`
  - `backend/app/Services/Workspace/*.php`
  - `backend/app/Services/Security/*.php`
  - `backend/app/Traits/*.php`
  - `backend/app/Policies/*.php`
  - `backend/app/Models/*.php`
  - `backend/tests/Feature/AuthTest.php`
  - `backend/tests/Feature/TenantIsolationTest.php`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter="AuthTest|TenantIsolationTest"`
* **Điều kiện nghiệm thu:** Workspace A tuyệt đối không thể đọc bài viết, xem credit, lấy API key của Workspace B kể cả qua HTTP API lẫn Queue Worker.

---

### [NHÓM 4] TASK-04: Hệ Thống Gói Dịch Vụ & Credit Theo Tháng
* **Ưu tiên:** P0.
* **Mục tiêu:** Quản lý credit và ngân sách chi phí AI minh bạch, kiểm soát chặt chẽ concurrency và hạn mức sử dụng.
* **Công việc chi tiết:**
  - `TASK-04.1`: Quản lý 4 gói dịch vụ:
    - *Free:* 0đ, 5 bài/ngày (cấp 100 credit/24h tính từ lần cấp gần nhất).
    - *Basic:* 450.000đ/tháng (100 bài/tháng, 2.000 credit/tháng).
    - *Pro:* 1.250.000đ/tháng (350 bài/tháng, 7.000 credit/tháng).
    - *Business:* 2.500.000đ/tháng (1.000 bài/tháng, 20.000 credit/tháng).
    - Định mức cơ sở: 20 credit/bài tiêu chuẩn (tính theo token/model thực tế).
  - `TASK-04.2`: Chu kỳ cấp credit:
    - Cấp khi kích hoạt gói, ghi nhận ngày bắt đầu/kết thúc chu kỳ.
    - Không cộng dồn credit còn dư sang tháng sau (reset theo chu kỳ).
    - Idempotency: Không cấp trùng credit nếu webhook/job chạy lại.
  - `TASK-04.3`: Cơ chế trừ credit:
    - Service quản lý credit tập trung, bọc `DB::transaction()`.
    - Kiểm tra số dư trước khi dispatch job AI/SERP.
    - Cơ chế hoàn credit nếu job thất bại hợp lệ.
    - Khóa pessimistic (`lockForUpdate`) chống trừ âm khi nhiều request đồng thời.
    - Cấm frontend tự quyết định số credit phải trừ.
  - `TASK-04.4`: Giới hạn sử dụng & chống vượt ngân sách:
    - Giới hạn số bài viết, tác vụ song song, số lần retry, số ảnh sinh.
    - Dừng nhận tác vụ mới khi hết credit.
* **Phụ thuộc:** TASK-02, TASK-03.
* **Tệp cho phép tác động (allowed_files):**
  - `backend/config/ai_costs.php`
  - `backend/app/DTOs/*.php`
  - `backend/app/Exceptions/**/*.php`
  - `backend/app/Services/Billing/*.php`
  - `backend/app/Services/Cost/*.php`
  - `backend/app/Models/*.php`
  - `backend/app/Console/Commands/*.php`
  - `backend/bootstrap/app.php`
  - `backend/database/seeders/*.php`
  - `backend/tests/Feature/CreditTest.php`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=CreditTest`
* **Điều kiện nghiệm thu:** Không thể dùng nhiều request đồng thời để vượt số dư credit hoặc khiến hệ thống thực hiện vô hạn tác vụ AI tốn tiền.

---

### [NHÓM 5] TASK-05: Thanh Toán Thủ Công & Trang Quản Trị Admin
* **Ưu tiên:** P0.
* **Mục tiêu:** Vận hành luồng thanh toán chuyển khoản thủ công kèm Admin xác nhận và kích hoạt credit tự động chống sai lệch.
* **Công việc chi tiết:**
  - `TASK-05.1`: Phía khách hàng:
    - Hiển thị bảng giá các gói và quyền lợi.
    - Tạo đơn hàng với mã tham chiếu duy nhất (VD: `SEOPRO_10293`).
    - Hiển thị thông tin chuyển khoản (STK, QR, cú pháp).
    - Cho phép khách hàng gửi minh chứng thanh toán.
    - Theo dõi trạng thái: Chờ thanh toán $\rightarrow$ Chờ xác nhận $\rightarrow$ Thành công / Từ chối.
  - `TASK-05.2`: Phía Admin:
    - Xem danh sách và chi tiết đơn hàng thanh toán.
    - Nút Duyệt / Từ chối thanh toán.
    - Tự động kích hoạt subscription và cấp credit ngay khi Admin duyệt.
    - Điều chỉnh credit thủ công có ghi chú lý do và log kiểm toán.
    - Khóa / mở khóa workspace.
  - `TASK-05.3`: Chống sai lệch giao dịch:
    - Một đơn hàng chỉ được kích hoạt duy nhất một lần (Idempotent lock).
    - Không cấp credit tự động chỉ dựa vào ảnh chuyển khoản khách tải lên.
    - Ghi nhận Admin ID, thời gian và số tiền xác nhận vào `audit_logs`.
* **Phụ thuộc:** TASK-04.
* **Tệp cho phép tác động (allowed_files):**
  - `backend/routes/api.php`
  - `backend/app/Http/Controllers/Api/V1/Billing/*.php`
  - `backend/app/Http/Controllers/Api/V1/Admin/*.php`
  - `backend/app/Http/Requests/**/*.php`
  - `backend/app/Http/Resources/*.php`
  - `backend/app/Policies/*.php`
  - `backend/app/Services/Billing/*.php`
  - `backend/app/Services/Security/*.php`
  - `backend/tests/Feature/PaymentOrderTest.php`
  - `frontend/app/(dashboard)/settings/subscription/**/*.tsx`
  - `frontend/app/(admin)/**/*.tsx`
  - `frontend/components/features/billing/**/*.tsx`
  - `frontend/hooks/*.ts`
  - `frontend/lib/**/*.ts`
  - `frontend/types/*.d.ts`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=PaymentOrderTest`
* **Điều kiện nghiệm thu:** Khách hàng tạo đơn $\rightarrow$ Chuyển khoản $\rightarrow$ Admin duyệt $\rightarrow$ Gói kích hoạt đúng hạn mức credit.

---

### [NHÓM 6] TASK-06: Hoàn Thiện Luồng Xử Lý AI & Tác Vụ Nền
* **Ưu tiên:** P0.
* **Mục tiêu:** Khép kín và ổn định toàn bộ chuỗi sinh bài tự động trong Horizon Queue, tích hợp trừ credit trước khi chạy.
* **Công việc chi tiết:**
  - `TASK-06.1`: Kiểm tra & tối ưu luồng import keyword từ Excel/CSV.
  - `TASK-06.2`: Sửa luồng Start Campaign để thực sự dispatch batch jobs.
  - `TASK-06.3`: Kết nối xuyên suốt: *SERP Research $\rightarrow$ Crawl Đối thủ $\rightarrow$ Lập Outline $\rightarrow$ Viết bài AI $\rightarrow$ Sinh/Tải ảnh $\rightarrow$ Tối ưu Internal Links & SEO*.
  - `TASK-06.4`: Kiểm tra cơ chế failover (OpenAI $\rightarrow$ Anthropic $\rightarrow$ Gemini) kèm backoff.
  - `TASK-06.5`: Xử lý timeout, retry có giới hạn và log lỗi API bên thứ ba.
  - `TASK-06.6`: Cập nhật trạng thái pipeline: pending, running, success, failed, cancelled.
  - `TASK-06.7`: Bổ sung cơ chế hủy tác vụ (Cancel Job) an toàn.
  - `TASK-06.8`: Chống thực thi trùng lặp job (Unique Job Lock).
  - `TASK-06.9`: Giám sát Horizon Queue & Dead Letter Queue.
  - `TASK-06.10`: Tích hợp kiểm tra và trừ credit trước khi bắt đầu job có phí.
  - `TASK-06.11`: Ghi log chi tiết token, model, latency và USD vào `ai_usage_logs`.
  - `TASK-06.12`: Hoàn thiện quy trình đẩy bài lên WordPress kèm xử lý lỗi kết nối REST API.
* **Phụ thuộc:** TASK-01, TASK-04.
* **Tệp cho phép tác động (allowed_files):**
  - `backend/config/ai.php`
  - `backend/config/horizon.php`
  - `backend/config/queue.php`
  - `backend/routes/api.php`
  - `backend/app/Providers/AI/**/*.php`
  - `backend/app/Exceptions/**/*.php`
  - `backend/app/Agents/**/*.php`
  - `backend/app/Jobs/**/*.php`
  - `backend/app/Events/*.php`
  - `backend/app/Listeners/**/*.php`
  - `backend/app/Console/Commands/*.php`
  - `backend/app/Http/Controllers/Api/V1/*.php`
  - `backend/app/Services/**/*.php`
  - `backend/app/DTOs/*.php`
  - `backend/tests/Feature/PipelineTest.php`
* **Lệnh kiểm thử:** `cd backend && ./vendor/bin/pint --test && php artisan test --filter=PipelineTest`
* **Điều kiện nghiệm thu:** Chiến dịch chạy thông suốt từ đầu đến cuối; lỗi ở bước nào thì cô lập tại bước đó, báo lỗi rõ ràng và không sinh bài trùng.

---

### [NHÓM 7] TASK-07: Hoàn Thiện Giao Diện SaaS & Dashboard
* **Ưu tiên:** P1.
* **Mục tiêu:** Cung cấp trải nghiệm SaaS hoàn chỉnh cho khách hàng và trang quản trị trực quan cho Admin.
* **Công việc chi tiết:**
  - `TASK-07.1`: Giao diện khách hàng:
    - Dashboard tổng quan số liệu bài viết, từ khóa, chiến dịch.
    - Trang quản lý gói cước, số dư credit và thanh toán chuyển khoản.
    - Trang quản lý danh sách WordPress sites.
    - Quản lý chiến dịch, pipeline bài viết thời gian thực.
    - Loading skeleton, empty state, error state, sonner toast.
  - `TASK-07.2`: Dashboard thống kê (Recharts):
    - Biểu đồ bài viết theo trạng thái.
    - Biểu đồ tiêu thụ credit theo ngày/tháng.
    - Chi phí AI thực tế theo website/workspace.
    - Cam kết: Chỉ dùng dữ liệu thật từ DB, không mock số liệu ảo.
  - `TASK-07.3`: Giao diện Admin:
    - Quản lý khách hàng, workspaces và websites.
    - Quản lý đơn hàng, duyệt thanh toán và cấp credit.
    - Xem chi phí AI toàn sàn và phát hiện lỗi queue.
    - Giao diện chuẩn mực responsive, bộ lọc, phân trang.
* **Phụ thuộc:** TASK-05, TASK-06.
* **Tệp cho phép tác động (allowed_files):**
  - `frontend/package.json`
  - `frontend/package-lock.json`
  - `frontend/.env.example`
  - `frontend/middleware.ts`
  - `frontend/app/**/*.tsx`
  - `frontend/components/**/*.tsx`
  - `frontend/hooks/**/*.ts`
  - `frontend/stores/**/*.ts`
  - `frontend/lib/**/*.ts`
  - `frontend/types/**/*.d.ts`
  - `backend/app/Http/Controllers/Api/V1/AnalyticsController.php`
  - `backend/app/Services/Analytics/*.php`
* **Lệnh kiểm thử:** `cd frontend && npm run build`
* **Điều kiện nghiệm thu:** Khách hàng tự theo dõi gói, credit, chiến dịch mượt mà; Admin thao tác duyệt đơn và kiểm soát chi phí không gặp lỗi vỡ giao diện.

---

### [NHÓM 8] TASK-08: Kiểm Thử, Bảo Mật & Nghiệm Thu Giai Đoạn 1
* **Ưu tiên:** P0 — Bắt buộc trước khi bàn giao / triển khai thực tế.
* **Mục tiêu:** Chạy toàn bộ test suite, kiểm tra bảo mật, cô lập tenant và nghiệm thu độc lập qua Reviewer Agent.
* **Công việc chi tiết:**
  - `TASK-08.1`: Kiểm thử Database & Migrations:
    - Chạy migration trên DB sạch, kiểm tra foreign keys và cascading.
    - Kiểm tra tính toàn vẹn transaction khi trừ/hoàn credit.
  - `TASK-08.2`: Kiểm thử Nghiệp vụ (End-to-End):
    - Đăng ký $\rightarrow$ Tạo workspace $\rightarrow$ Chọn gói $\rightarrow$ Thanh toán $\rightarrow$ Admin duyệt $\rightarrow$ Cấp credit $\rightarrow$ Viết bài AI $\rightarrow$ Đăng WordPress $\rightarrow$ Trừ credit.
    - Kiểm tra reset credit chu kỳ sau (gói Free mỗi ngày, gói trả phí mỗi tháng).
  - `TASK-08.3`: Kiểm thử Bảo mật & Tải cao:
    - Kiểm thử cách ly dữ liệu giữa các workspace (chặn IDOR).
    - Thử nghiệm request đồng thời trừ credit (Concurrency / Race condition).
    - Kiểm tra rò rỉ secret trong response API hoặc log.
  - `TASK-08.4`: Review độc lập & Nghiệm thu:
    - `reviewer-agent` thẩm định toàn bộ checklist 14 tiêu chí.
    - Cập nhật tài liệu vận hành và khôi phục hệ thống.
* **Phụ thuộc:** Tất cả các task từ TASK-01 đến TASK-07.
* **Tệp cho phép tác động (allowed_files):**
  - `backend/tests/**/*.php`
  - `qlpk-autonomous-framework/reports/tasks/PHASE_1_COMPLETION_REPORT.md`
* **Lệnh kiểm thử:** `cd backend && php artisan test && cd ../frontend && npm run build`
* **Điều kiện nghiệm thu:** Tất cả test tự động pass (`exit_code == 0`), không còn lỗi bảo mật cách ly tenant hay lỗi cấp/trừ credit.
