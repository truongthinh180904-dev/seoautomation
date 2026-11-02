# TÀI LIỆU BÀN GIAO NGỮ CẢNH DỰ ÁN (HANDOVER CONTEXT)
## HỆ THỐNG SAAS VIẾT BÀI SEO TỰ ĐỘNG (LARAVEL 13 + NEXT.JS 16)
### BỘ ĐIỀU PHỐI ĐA AGENT: QLPK SAFE AUTONOMOUS FRAMEWORK

> **Mục đích:** Tài liệu này ghi lại trọn vẹn 100% ngữ cảnh kiến trúc, cấu hình phân chia Model cho từng Agent, quy trình vận hành và lộ trình 8 nhóm task Giai đoạn 1. Dùng để nạp ngay lập tức vào phiên làm việc mới khi chuyển đổi tài khoản AI / mức Quota mới mà không bị gián đoạn hay mất dấu vết.

---

## 1. PHÂN CHIA VAI TRÒ AGENT & ĐỊNH TUYẾN MODEL (MODEL ROUTING CHUẨN)

Hệ thống tuân thủ mô hình phân tách 4 vai trò độc lập theo đúng bản đặc tả:

| Vai trò (Role) | Model Định Tuyến | Chế độ CLI (`--mode`) | Công cụ được cấp | Trách nhiệm chính |
| :--- | :--- | :--- | :--- | :--- |
| **PLANNER** | **`gemini-4-argon-medium`** | `plan` (Chỉ đọc) | Read-only (đọc source, không sửa code) | Đọc yêu cầu dự án, chia task nguyên tử (5-15p), xác định dependencies và danh sách `allowed_files`, duy trì `ROADMAP.md` & `PROGRESS.md`. |
| **CODER** | **`gemini-3.8-flash-high`** | `accept-edits` (Ghi file) | `write_file`, `edit_file` (CẤM shell phá hủy) | Đọc đặc tả task, viết code đúng phạm vi `allowed_files` theo `skills/`. Không tự nhận mình đã hoàn thành. |
| **AUTOMATED TESTER** | **Shell Runner** (Bash thật, KHÔNG dùng LLM) | Thực thi trực tiếp trong Sandbox | Chạy lệnh test thật qua Bash | Chạy `php artisan test`, `npm run build`, `pint --test`. Lấy mã thoát `exit_code == 0` làm bằng chứng nghiệm thu thực tế. |
| **REVIEWER** | **`gemini-4-argon-medium`** | `plan` (Chỉ đọc) | Read-only | Đọc `git diff` và file test report. Kiểm duyệt **14 tiêu chí Anti-Fake-Done**, chỉ cấp JSON `{"verdict": "PASS"}` khi đạt chuẩn 100%. |
| **DEBUG FALLBACK** | **`gemini-3.8-flash-high`** | `accept-edits` / `plan` | Ghi file / Đọc | Tự động kích hoạt thay thế khi Reviewer hoặc Coder gặp lỗi Quota/Rate Limit (429 Resource Exhausted) sau khi lùi bước (backoff). |

---

## 2. THÔNG TIN DỰ ÁN & TECH STACK

* **Tên dự án:** Hệ thống SaaS Viết Bài SEO Tự Động (Multi-Tenant Auto-SEO SaaS).
* **Mã nguồn dự án:**
  * Backend: `backend/` — Laravel 12/13 (PHP 8.3), PostgreSQL, Redis, Laravel Horizon, Laravel Sanctum, Spatie Permission, PhpSpreadsheet.
  * Frontend: `frontend/` — Next.js 16 (App Router), React 19, TypeScript strict, Tailwind CSS v4, shadcn/ui, TanStack Query v5, Zustand, Lexical Rich Text Editor, Recharts.
  * AI & Dịch vụ ngoài: OpenAI API, Google Gemini, Anthropic Claude, SerpAPI, Pexels/Unsplash, WordPress REST API.
* **Thư mục khung điều phối tự động:** `qlpk-autonomous-framework/` (đã thiết lập đầy đủ).

---

## 3. HIỆN TRẠNG ĐÃ THIẾT LẬP HOÀN TẤT (100% SẴN SÀNG)

Toàn bộ khung điều phối tự động theo chuẩn **QLPK Safe Autonomous Framework Spec** đã được khởi tạo và kiểm tra:

1. **Script điều hành chính ([`qlpk-autonomous-framework/run-agy.sh`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/run-agy.sh)):**
   * Đã cấu hình biến Model: `PLANNER_MODEL=gemini-4-argon-medium`, `CODER_MODEL=gemini-3.8-flash-high`, `REVIEWER_MODEL=gemini-4-argon-medium`, `DEBUG_FALLBACK_MODEL=gemini-3.8-flash-high`.
   * Đã tích hợp `caffeinate -dimsu -w $$ &`: **Chống tắt máy, chống ngủ màn hình, chống unmount ổ đĩa** khi gập máy Mac chạy xuyên đêm.
   * Cảm biến bảo vệ tài nguyên phần cứng: Tự kiểm tra tải CPU (`<= 3.0/core`) và RAM trống (`>= 15%`), tự động nghỉ 15s chờ hạ nhiệt nếu máy bị quá tải, chống nóng máy và chống tràn RAM (OOM).
   * Cổng kiểm duyệt thực tế **Anti-Fake-Done Gate (`verify_done_gate`)**: Bắt buộc đồng thời có file `reports/tasks/<task_id>-test.md` (`exit_code == 0` từ lệnh thật) và `reports/tasks/<task_id>-review.md` (`"verdict": "PASS"`) khớp đúng `RUN_ID` phiên hiện tại.
   * Bộ lọc an toàn chặn đứng lệnh phá hủy (`migrate:fresh`, `db:wipe`, `git reset --hard`, `DROP/TRUNCATE TABLE`).

2. **Hệ thống tài liệu điều phối chuẩn mực:**
   * [`AGENTS.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/AGENTS.md): Phân tách 4 vai trò độc lập và quy định cụ thể `gemini-4-argon-medium` & `gemini-3.8-flash-high`.
   * [`PLANNER.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/PLANNER.md): Quy chuẩn chia task nguyên tử (5–15 phút), giới hạn `allowed_files` và máy trạng thái task.
   * [`REVIEWER.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/REVIEWER.md): Checklist 14 tiêu chí vàng kiểm duyệt code nghiêm ngặt.
   * [`ROADMAP.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/ROADMAP.md): Đặc tả chi tiết 8 nhóm task Giai đoạn 1.
   * [`PROGRESS.md`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/PROGRESS.md): Bảng ma trận theo dõi tiến độ thời gian thực.
   * [`templates/`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/templates): Mẫu báo cáo test, review và audit.
   * [`skills/`](file:///Users/truongthinh/Documents/SaasAutoSeo/qlpk-autonomous-framework/skills): Cẩm nang kỹ thuật cho Laravel Backend, Next.js Frontend và API Contract.

---

## 4. LỘ TRÌNH 8 NHÓM TASK GIAI ĐOẠN 1 (ĐANG CHỜ THỰC HIỆN)

Hệ thống được chia thành 8 đợt chạy tuần tự:

| Đợt | Mã Task | Tên Nhóm Task | Mục tiêu & Đầu ra | Trạng thái |
| :---: | :--- | :--- | :--- | :---: |
| **Đợt 1** | **TASK-01** | **Audit Code & Database Hiện Tại** | Khảo sát toàn diện hệ thống $\rightarrow$ Xuất báo cáo bắt buộc **`PHASE_1_AUDIT.md`**. Chưa xóa/sửa code cũ. | **SẴN SÀNG CHẠY** |
| **Đợt 2** | **TASK-02** | **Thiết Kế & Khởi Tạo Database SaaS** | Tạo migrations mới (`workspaces`, `websites`, `plans`, `credit_accounts`, `usage_records`), bảo toàn bảng cũ. | PENDING |
| **Đợt 3** | **TASK-03** | **Tài Khoản, Workspace & Bảo Mật** | Cô lập dữ liệu Multi-Tenant tuyệt đối, Sanctum token expiry, chống IDOR cả trên API lẫn Queue. | PENDING |
| **Đợt 4** | **TASK-04** | **Gói Dịch Vụ & Sổ Cái Credit Theo Tháng** | Gói Free (100 cr/ngày), Basic (2k), Pro (7k), Business (20k). Cơ chế trừ credit bọc transaction, chống trừ âm. | PENDING |
| **Đợt 5** | **TASK-05** | **Thanh Toán Thủ Công & Admin** | Khách chuyển khoản $\rightarrow$ Admin duyệt $\rightarrow$ Cấp credit tự động (Idempotency chống cấp trùng). | PENDING |
| **Đợt 6** | **TASK-06** | **Hoàn Thiện Luồng AI & Tác Vụ Nền** | Nối thông SERP $\rightarrow$ Crawl $\rightarrow$ Outline $\rightarrow$ Write $\rightarrow$ Image $\rightarrow$ WP. Kiểm tra credit trước khi chạy. | PENDING |
| **Đợt 7** | **TASK-07** | **Giao Diện SaaS & Dashboard Thống Kê** | Hoàn thiện dashboard khách và admin; biểu đồ Recharts số liệu thật từ DB; responsive table + form guard. | PENDING |
| **Đợt 8** | **TASK-08** | **Kiểm Thử, Bảo Mật & Nghiệm Thu** | Pest PHP test suite, test race condition trừ credit, Reviewer sign-off 14 tiêu chí pass 100%. | PENDING |

---

## 5. BỘ PROMPT KÍCH HOẠT NHANH KHI ĐỔI TÀI KHOẢN (COPY & PASTE)

Khi mở phiên mới ở tài khoản khác, fen chỉ cần **copy nguyên văn đoạn văn bản dưới đây** và gửi cho Agent:

```markdown
Chào bạn, tôi đang tiếp tục dự án SaaS Viết Bài SEO Tự Động (Laravel 13 + Next.js 16) được điều phối bởi QLPK Safe Autonomous Framework.
Toàn bộ mã nguồn và cấu hình đã có sẵn trong workspace:
1. Đọc kỹ file HANDOVER_CONTEXT.md và thư mục qlpk-autonomous-framework/.
2. Định tuyến rõ vai trò model theo đúng spec:
   - PLANNER: Model gemini-4-argon-medium (mode: plan, chỉ đọc).
   - CODER: Model gemini-3.8-flash-high (mode: accept-edits, chỉ ghi file trong allowed_files).
   - AUTOMATED TESTER: Shell runner thực thi lệnh test thật trong sandbox (lấy exit_code == 0).
   - REVIEWER: Model gemini-4-argon-medium (mode: plan, kiểm duyệt 14 tiêu chí Anti-Fake-Done).
   - DEBUG FALLBACK: Model gemini-3.8-flash-high (dự phòng khi gặp 429).
3. Bắt đầu ngay Đợt 1 — TASK-01: Thực hiện khảo sát toàn diện mã nguồn backend, frontend và database hiện tại, sau đó xuất ra tài liệu bắt buộc PHASE_1_AUDIT.md theo đúng 7 mục quy định.
4. Tuyệt đối không xóa bảng hay sửa đổi logic cũ đang chạy tốt khi chưa hoàn thành báo cáo audit.
Hãy xác nhận đã nắm toàn bộ cấu hình model và bắt đầu triển khai TASK-01 ngay!
```

---

## 6. LỆNH VẬN HÀNH NGOÀI TERMINAL (CHẠY QUA ĐÊM KHÔNG TẮT MÁY)

Khi muốn chạy script nền độc lập trên máy Mac:

```bash
# 1. Cấp quyền thực thi cho script:
chmod +x ./qlpk-autonomous-framework/run-agy.sh

# 2. Chạy thử kiểm tra môi trường và cấu hình Model:
./qlpk-autonomous-framework/run-agy.sh dry-run

# 3. Xem trạng thái tiến trình, model routing và tài nguyên:
./qlpk-autonomous-framework/run-agy.sh status

# 4. Khởi chạy tiến trình ngầm qua đêm (chống tắt màn hình / chống sleep):
nohup caffeinate -dimsu env FORCE_RUN=1 CPU_LOAD_PER_CORE_LIMIT=3.0 MAX_TASKS_PER_SESSION=50 SESSION_TIMEOUT_SECONDS=43200 ./qlpk-autonomous-framework/run-agy.sh run > ./qlpk-autonomous-framework/logs/overnight.out 2>&1 &

# 5. Theo dõi tiến độ thời gian thực:
tail -f ./qlpk-autonomous-framework/logs/overnight.out

# 6. Dừng an toàn (Graceful Stop):
./qlpk-autonomous-framework/run-agy.sh stop
```
