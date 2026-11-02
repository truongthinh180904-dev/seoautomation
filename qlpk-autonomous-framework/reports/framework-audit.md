# Báo cáo Audit Toàn diện: QLPK Autonomous Framework (19 Files)

- **Ngày thực hiện audit:** `2026-10-26T16:52:08+07:00`
- **Thư mục được kiểm toán:** `/Users/truongthinh/Documents/UIT/Manage Infomation/clinic-management-system/qlpk-autonomous-framework/`
- **Project Root:** `/Users/truongthinh/Documents/UIT/Manage Infomation/clinic-management-system/`
- **Phiên bản AGY CLI thực tế trên máy:** `1.4.7`
- **Hệ điều hành / Tài nguyên máy chủ kiểm tra:** macOS (`hw.ncpu = 4`, `hw.memsize = 17179869184` bytes / 16 GB, `vm.loadavg = { 6.33 4.37 4.05 }`)
- **Phương pháp kiểm toán:** Giai đoạn 1 Read-Only Static Inspection + Safe Non-Destructive CLI/OS Probing (`bash -n`, `agy --version`, `agy --help`, `agy models`, `agy agents`, `git status`, `sysctl`, `vm_stat`). Không gọi vòng lặp agent tự động, không sửa bất kỳ file hiện có nào.

---

## A. Executive Summary

### 1. Thống kê tổng quan 19 file thực tế
Trong thư mục `qlpk-autonomous-framework/` có tổng cộng **21 entry hệ thống file**, bao gồm **2 file metadata của macOS** (`.DS_Store`, `skills/.DS_Store`) và **19 file thực tế của framework** (không tính file `qlpk-autonomous-framework-v2.zip` nằm ở ngoài project root):

| Phân loại đánh giá | Số lượng file | Tỷ lệ | Giải thích |
|---|---:|---:|---|
| **PASS** | **9** | 47.4% | 6 file `SKILL.md` (`api-contract`, `database-migrations`, `laravel-backend`, `nextjs-frontend`, `security-privacy`, `testing-quality`) và 3 file template (`TASK_TEMPLATE.md`, `REVIEW_REPORT_TEMPLATE.md`, `FINAL_REPORT_TEMPLATE.md`) đạt yêu cầu về quy tắc kỹ thuật, cấu trúc báo cáo và cổng kiểm soát chất lượng. |
| **PARTIAL** | **8** | 42.1% | `README.md`, `AGENTS.md`, `PLANNER.md`, `REVIEWER.md`, `ROADMAP.md`, `PROGRESS.md`, `MORNING.md`, `reports/README.md` có mô tả quy trình tốt nhưng còn mâu thuẫn giữa kiến trúc theo **Feature** so với theo **Task**, dùng tên hiển thị model thay vì model ID thực tế của `agy`, giả định copy ra project root thay vì chạy từ thư mục con `qlpk-autonomous-framework/`, và thiếu quy định định lượng về CPU/RAM/cooldown. |
| **FAIL** | **2** | 10.5% | `run-agy.sh` (mới là scaffold 90 dòng, hoàn toàn chưa có bộ điều phối model, cổng kiểm thử/review thực tế, kiểm tra CPU/RAM, cooldown, timeout, backoff hay bảo vệ Git) và `reports/agy-cli-capability-matrix.md` (toàn bộ 12 hạng mục đang để `Pending` / `NOT_VERIFIED`). |
| **NOT VERIFIED (Runtime)** | **0** *(file)* / **11** *(test cases)* | — | Toàn bộ 19 file đã được đọc và kiểm tra tĩnh 100% (`STATICALLY INSPECTED`), cú pháp `run-agy.sh` đã kiểm tra (`EXECUTED AND PASSED` với `bash -n`), nhưng các kịch bản runtime điều phối agent chưa chạy thật do `run-agy.sh` chưa cài đặt logic thực thi. |
| **Tổng cộng** | **19** | **100%** | — |

### 2. Kết luận khả năng chạy tự động hiện tại
- **Framework hiện CHƯA THỂ chạy tự động.** Lệnh `./run-agy.sh run` đang chủ đích chặn cứng với `exit 20` (`BLOCKED: AGY adapter is not implemented`).
- **Mức độ sẵn sàng:** **`NOT READY`** (chỉ ở mức *Governance & Documentation Scaffold*).

### 3. Năm rủi ro quan trọng nhất (Top 5 Risks)
1. **[CRITICAL - P0] Hoàn toàn thiếu cơ chế bảo vệ CPU, RAM, Cooldown, Timeout và Quota Backoff trong `run-agy.sh`:** Script không kiểm tra load average (`sysctl -n vm.loadavg` / `hw.ncpu` hiện đang ở mức cao `6.33 / 4 = 1.58 load/core`), không kiểm tra RAM khả dụng (`vm_stat`), không có `TASK_COOLDOWN_SECONDS`, `ERROR_COOLDOWN_SECONDS`, `QUOTA_BACKOFF_SECONDS`, hay `TASK_TIMEOUT_SECONDS`.
2. **[CRITICAL - P0] Chưa có Adapter gọi `agy` CLI và kiểm soát Model Routing thực tế:** Các file Markdown chỉ ghi tên chung ("Gemini 3.8 Flash", "Gemini 4 Argon") thay vì model ID chính xác (`claude-sonnet-5-5-high`, `gemini-3.8-flash-high`, `gemini-4-argon-high`, `claude-opus-5-5-high`), và `run-agy.sh` chưa có bất kỳ hàm nào gọi `agy --model <id> --print --print-timeout <sec>` hay phân loại lỗi CLI/quota/network/timeout.
3. **[HIGH - P0] Chưa có cổng thực thi Automated Tester → Reviewer → Fix Loop và chống trạng thái `DONE` giả bằng code:** Các quy tắc "Test FAIL không gọi Reviewer", "Tối đa 3 vòng review", "Chống DONE giả / log cũ" mới tồn tại trên văn bản Markdown và dòng `log` giả lập trong `dry_run()`, chưa có logic kiểm tra exit code thật hay đối chiếu hash/timestamp báo cáo.
4. **[HIGH - P1] Lệch kiến trúc đường dẫn (`FRAMEWORK_DIR` vs `PROJECT_ROOT`) và tác dụng phụ khi chạy lệnh read-only:** `README.md` giả định trộn toàn bộ file vào project root, trong khi thực tế bộ khung nằm tại `clinic-management-system/qlpk-autonomous-framework/`. `run-agy.sh` đặt `ROOT_DIR` là thư mục chứa script nhưng các lệnh trong `ROADMAP.md` lại tham chiếu `be/`, `fe/` ở project root. Ngoài ra, dòng 18–19 của `run-agy.sh` tự động `mkdir -p` `.agy-state` và `logs` ngay cả khi chỉ gọi `./run-agy.sh status` hoặc `--help`.
5. **[HIGH - P1] Thiếu cổng bảo vệ Git working tree, lệch cấu hình nhánh (`dev` vs `main`) và nguy cơ chạm DB thật `DBQLPK`:** `AGENTS.md` yêu cầu làm việc trên nhánh `dev`, nhưng repo hiện tại đang ở nhánh `main` với working tree có file untracked. `run-agy.sh` không kiểm tra `git status --porcelain` trước khi chạy và không khóa phạm vi `allowed_files`. Dù `be/phpunit.xml` đã cấu hình SQLite `:memory:`, nếu Coder agent chạy `php artisan migrate:fresh` trực tiếp sẽ tác động thẳng vào `DBQLPK` trong `be/.env`.

---

## B. File Inventory (Kiểm kê chi tiết 19 file)

| # | File path (tương đối từ `qlpk-autonomous-framework/`) | Mục đích | Trạng thái | Bằng chứng cụ thể (Static / Executed) | Vấn đề phát hiện | Hành động đề xuất |
|---:|---|---|---|---|---|---|
| 1 | `README.md` | Giới thiệu bộ khung scaffold, thành phần và lưu ý an toàn. | **PARTIAL** | `STATICALLY INSPECTED` (18 dòng). Dòng 3: *"Copy/merge the contents into the repository root"*. Dòng 15: `run-agy.sh run` bị chặn có chủ đích. | Giả định copy toàn bộ file ra project root, mâu thuẫn với yêu cầu giữ nguyên cấu trúc trong `clinic-management-system/qlpk-autonomous-framework/`. | Cập nhật hướng dẫn chạy trực tiếp từ `qlpk-autonomous-framework/run-agy.sh` mà không cần trộn file vào project root. |
| 2 | `AGENTS.md` | Quy tắc điều phối tổng thể, vai trò model, vòng đời, quy tắc code senior, Git và báo cáo. | **PARTIAL** | `STATICALLY INSPECTED` (112 dòng). Dòng 33–41: mô tả model chung. Dòng 45–58: vòng đời theo Feature. Dòng 76: yêu cầu nhánh `dev`. | 1) Dùng tên chung ("Gemini 3.8 Flash", "Gemini 4 Argon") thay vì ID thật (`gemini-3.8-flash-high`, `gemini-4-argon-high`, `claude-sonnet-5-5-high`, `claude-opus-5-5-high`).<br>2) Không gắn `claude-sonnet-5-5-high` làm mặc định cho `PLANNER`.<br>3) Quy định gọi Reviewer theo *Feature* thay vì từng *Task* (mâu thuẫn mục 3 & 4B).<br>4) Thiếu mục quy định ngưỡng CPU/RAM/cooldown.<br>5) Cố định nhánh `dev` trong khi repo đang dùng `main`. | Chuẩn hóa bảng Model ID theo `agy models`; đồng bộ cổng Review theo từng Task (hoặc hỗ trợ cả Task & Feature gate); bổ sung chính sách CPU/RAM/Cooldown và cấu hình branch linh hoạt (`ALLOWED_BRANCHES`). |
| 3 | `PLANNER.md` | Quy trình phân rã yêu cầu thành feature/task, Definition of Ready và quy tắc model cho Planner. | **PARTIAL** | `STATICALLY INSPECTED` (37 dòng). Dòng 18–21: chỉ ghi *"Dùng model mạnh cho planning kiến trúc lớn"*. | Không chỉ định rõ model mục tiêu của Planner là `claude-sonnet-5-5-high`; chưa yêu cầu khai báo đường dẫn dựa trên `PROJECT_ROOT` vs `FRAMEWORK_DIR`. | Cập nhật quy tắc model của Planner thành `claude-sonnet-5-5-high` và yêu cầu chuẩn hóa đường dẫn `allowed_files` tính từ `PROJECT_ROOT`. |
| 4 | `REVIEWER.md` | Quy tắc kiểm duyệt độc lập, đầu vào bắt buộc, checklist 14 điểm, định dạng finding và fallback. | **PARTIAL** | `STATICALLY INSPECTED` (70 dòng). Dòng 4: *"Chỉ gọi sau khi tất cả task con hoàn thành... Không gọi sau từng task nhỏ"*. Dòng 64–67: fallback sang Claude Sonnet 5.5 / Opus 5.5. | 1) Cấm gọi Reviewer sau từng task (mâu thuẫn với mục 3 & 4B: mỗi task phải qua CODER → TESTER → REVIEWER → FIX LOOP tối đa 3 vòng).<br>2) Chưa ghi chính xác model ID (`gemini-4-argon-high`, `claude-sonnet-5-5-high`, `claude-opus-5-5-high`). | Sửa dòng 4 để cho phép gọi Reviewer ở cấp độ Task sau khi các kiểm thử bắt buộc của Task đều PASS; cập nhật chính xác Model ID. |
| 5 | `ROADMAP.md` | Danh sách feature/task kế hoạch (hiện chứa `F-DEMO-001` gồm `T-DEMO-001..003`). | **PARTIAL** | `STATICALLY INSPECTED` (76 dòng). Dòng 19–71: khai báo 3 task demo với status `DRAFT`. Dòng 36, 53, 69: tham chiếu `reports/tasks/T-DEMO-001..003.md` và `reports/workflow-dry-run.md` (chưa tồn tại). | 1) Trường `model` ghi câu mô tả thay vì model ID `gemini-3.8-flash-high`.<br>2) `test_commands` trộn lẫn ngữ cảnh thư mục (`be/`, `fe/`, và `bash -n run-agy.sh` tại framework root). | Chuẩn hóa trường `model` thành ID chuẩn, tách rõ `workdir` (`PROJECT_ROOT`, `be/`, `fe/`, `FRAMEWORK_DIR`) cho từng `test_command`. |
| 6 | `PROGRESS.md` | Bảng theo dõi trạng thái thực thi của feature và từng task. | **PARTIAL** | `STATICALLY INSPECTED` (32 dòng). Dòng 22–24: cột `Review status` của `T-DEMO-001..003` đều ghi `NOT_APPLICABLE`. | Đặt `Review status = NOT_APPLICABLE` cho các task và thiếu cột lưu `Model Used`, `Run Duration`, `Fallback Reason`, `Timestamp/Run ID` để chống trạng thái `DONE` giả theo mục 4B. | Đổi `Review status` mặc định của task thành `NOT_RUN`; bổ sung cột `Model`, `Duration`, `Fallback`, `Evidence Path` và `Task Status: DONE`. |
| 7 | `MORNING.md` | Mẫu báo cáo tóm tắt hàng ngày cho người dùng. | **PARTIAL** | `STATICALLY INSPECTED` (28 dòng). Tất cả chỉ số đang ở trạng thái khởi tạo `0` / `NOT_RUN`. | Chỉ theo dõi ở mức Feature, chưa có bảng tổng hợp mức tiêu thụ tài nguyên (CPU/RAM waits, cooldowns, quota backoffs, fallback count) và chi tiết theo Task. | Bổ sung mục thống kê Task, Model Invocations, Fallback Events và Resource/Quota Throttling. |
| 8 | `run-agy.sh` | Script shell điều phối chính của framework. | **FAIL** | `STATICALLY INSPECTED` (90 dòng) & `EXECUTED AND PASSED` cú pháp (`bash -n qlpk-autonomous-framework/run-agy.sh` → `exit_code=0`). | 1) Chỉ là khung rỗng (`run` thoát với mã `20`).<br>2) Không phân biệt `FRAMEWORK_DIR` và `PROJECT_ROOT`.<br>3) Tự tạo `.agy-state` và `logs` ở dòng 18 ngay cả khi chạy `status`/`--help`.<br>4) Hoàn toàn thiếu: gọi `agy` theo vai trò, kiểm tra CPU/RAM, cooldown, error/quota backoff, timeout, trap `INT`/`TERM`, stale lock check, Git dirty tree check, automated test runner và cổng review 3 vòng. | Nâng cấp `run-agy.sh` theo bản thiết kế ở Mục D, E, G và H (chỉ thực hiện sau khi được phê duyệt). |
| 9 | `reports/README.md` | Quy định cấu trúc thư mục lưu trữ báo cáo. | **PARTIAL** | `STATICALLY INSPECTED` (13 dòng). Đề xuất `tasks/<task_id>.md`, `features/...`. | Chỉ quy định review report ở cấp `features/<feature_id>-review-round-<n>.md`, thiếu đường dẫn chuẩn cho review report cấp task (`tasks/<task_id>-review-round-<n>.md`) và báo cáo audit (`framework-audit.md`). | Bổ sung quy ước lưu `tasks/<task_id>-test.md`, `tasks/<task_id>-review-round-<n>.md` và `framework-audit.md`. |
| 10 | `reports/agy-cli-capability-matrix.md` | Bảng xác minh khả năng thực tế của AGY CLI. | **FAIL** | `STATICALLY INSPECTED` (23 dòng). Toàn bộ 12 dòng trong bảng đều đang ghi `Pending` và `NOT_VERIFIED`. | Chưa cập nhật bằng chứng thực tế từ `agy` `1.4.7` đã cài trên máy (`--model`, `--print`, `--print-timeout`, `--output-format`, `--json-schema`, `--effort`, `--mode`, `--sandbox`). | Cập nhật đầy đủ kết quả xác minh `agy 1.4.7` (chi tiết đã thu thập tại Mục D của báo cáo này). |
| 11 | `skills/api-contract/SKILL.md` | Quy tắc thiết kế và kiểm thử hợp đồng API giữa FE và BE. | **PASS** | `STATICALLY INSPECTED` (12 dòng). Đầy đủ 10 quy tắc về schema, mã lỗi HTTP (`400..500`), phân quyền resource và kiểm thử. | Không có mâu thuẫn. | Giữ nguyên; đảm bảo Coder/Reviewer nạp file này khi task liên quan API. |
| 12 | `skills/database-migrations/SKILL.md` | Quy tắc an toàn schema MySQL `DBQLPK` và Laravel migrations. | **PASS** | `STATICALLY INSPECTED` (18 dòng). Cấm `migrate:fresh`, `db:wipe`, xóa bảng/dữ liệu và yêu cầu chạy test migration trên DB tách biệt. | Không có mâu thuẫn nội dung; cần được cưỡng chế bằng bộ lọc lệnh nguy hiểm trong `run-agy.sh`. | Giữ nguyên nội dung; bổ sung guard chặn lệnh `migrate:fresh`/`db:wipe` trong `run-agy.sh`. |
| 13 | `skills/laravel-backend/SKILL.md` | Quy chuẩn kỹ thuật cho Backend Laravel 13 / PHP 8.3 / PHPUnit 12. | **PASS** | `STATICALLY INSPECTED` (30 dòng). Bao phủ Form Request, thin controller, N+1, transaction, policy/gate, decimal cho tiền tệ, Pint và completion gate. | Không có mâu thuẫn. | Giữ nguyên. |
| 14 | `skills/nextjs-frontend/SKILL.md` | Quy chuẩn kỹ thuật cho Frontend Next.js 16.4.2 / React 19 / Tailwind v4 / TS 5. | **PASS** | `STATICALLY INSPECTED` (29 dòng). Bao phủ Server/Client Components, bảo mật biến môi trường (`NEXT_PUBLIC_`), `cacheComponents`, accessibility và completion gate. | Không có mâu thuẫn. | Giữ nguyên. |
| 15 | `skills/security-privacy/SKILL.md` | Quy tắc bảo mật, kiểm soát truy cập và bảo vệ dữ liệu y tế/bệnh nhân. | **PASS** | `STATICALLY INSPECTED` (18 dòng). Bao phủ bảo vệ `.env`, chống IDOR/XSS/SQLi/mass-assignment, cấm log PII/dữ liệu bệnh nhân và chặn approve khi còn lỗi CRITICAL/HIGH. | Không có mâu thuẫn. | Giữ nguyên; tích hợp hàm redact secret vào logger của `run-agy.sh`. |
| 16 | `skills/testing-quality/SKILL.md` | Nguyên tắc cổng kiểm thử tự động và vòng lặp review/fix. | **PASS** | `STATICALLY INSPECTED` (31 dòng). Xác định rõ kết quả test là kết quả tiến trình (exit code), cấm biến `NOT_RUN` thành `PASS`, giới hạn 3 vòng review. | Ghi chú nhỏ ở dòng 20, 30 dùng từ "feature", nhưng nguyên lý cổng kiểm thử hoàn toàn chính xác. | Giữ nguyên (có thể mở rộng thuật ngữ "feature/task" khi đồng bộ `AGENTS.md`). |
| 17 | `templates/FINAL_REPORT_TEMPLATE.md` | Biểu mẫu báo cáo nghiệm thu cuối cùng. | **PASS** | `STATICALLY INSPECTED` (39 dòng). Có đầy đủ bảng AC evidence, bảng Tests (Command, Exit code, Result, Report/log), Findings, Files changed, Rollback. | Không có lỗi cấu trúc. | Giữ nguyên (hỗ trợ dùng cho cả Task và Feature). |
| 18 | `templates/REVIEW_REPORT_TEMPLATE.md` | Biểu mẫu báo cáo kết quả của Reviewer. | **PASS** | `STATICALLY INSPECTED` (51 dòng). Có `Review round: 1/3`, `Overall result: PASS | FAIL | BLOCKED`, cấu trúc finding chi tiết và checklist 14 mục. | Không có lỗi cấu trúc. | Giữ nguyên. |
| 19 | `templates/TASK_TEMPLATE.md` | Biểu mẫu đặc tả một Task chuẩn. | **PASS** | `STATICALLY INSPECTED` (55 dòng). Có đủ trường metadata (`role`, `model`, `status`, `dependencies`), `Allowed files`, `Acceptance criteria`, bảng `Test commands`, `Data/security impact` và `Completion evidence`. | Trong `Allowed Statuses` của `PROGRESS.md` chưa có `DONE` cho Task (mới có `PASS`), nên đồng bộ `DONE` giữa `TASK_TEMPLATE.md` và `PROGRESS.md`. | Giữ nguyên cấu trúc; bổ sung trường `run_id` / `duration_seconds` nếu cần tự động hóa. |

> **Lưu ý về các file được tham chiếu nhưng chưa tồn tại trên đĩa:**
> - `reports/tasks/T-DEMO-001.md`, `reports/tasks/T-DEMO-002.md`, `reports/tasks/T-DEMO-003.md`, `reports/workflow-dry-run.md` (được khai báo trong `ROADMAP.md` và `reports/README.md`): chưa tồn tại vì các task demo đang ở trạng thái `DRAFT` / `NOT_RUN`.
> - `.agy-state/STOP`, `.agy-state/STOP_AT_HOUR`, `.agy-state/run.lock`, `logs/orchestrator.log` (được tham chiếu trong `run-agy.sh`): là file runtime sẽ sinh ra khi chạy script.

---

## C. Requirement Traceability Matrix (Ma trận truy vết yêu cầu)

| Req ID | Mô tả yêu cầu | File chịu trách nhiệm | Bằng chứng cụ thể | Kết quả | Hành động khắc phục |
|---|---|---|---|---|---|
| **REQ-ARCH-01** | Đủ 6 vai trò/pha độc lập: `PLANNER`, `CODER`, `AUTOMATED TESTER`, `REVIEWER`, `FIX LOOP`, `FINAL APPROVAL`. | `AGENTS.md`, `PLANNER.md`, `REVIEWER.md`, `run-agy.sh` | `AGENTS.md:35-40, 45-58` mô tả đủ 6 pha; `run-agy.sh:49` chỉ in chuỗi log mô tả trong `dry_run()`. | **PARTIAL** | Cài đặt state machine thực thi đủ 6 pha trong `run-agy.sh`. |
| **REQ-MOD-01** | Cấu hình đúng model mục tiêu: Planner (`claude-sonnet-5-5-high`), Coder (`gemini-3.8-flash-high`), Reviewer (`gemini-4-argon-high`), Debug fallback (`claude-sonnet-5-5-high`), Arch/Sec (`claude-opus-5-5-high`). | `AGENTS.md`, `PLANNER.md`, `REVIEWER.md`, `ROADMAP.md`, `run-agy.sh` | `AGENTS.md:33-40` chỉ ghi tên dòng model chung ("Gemini 3.8 Flash", "Gemini 4 Argon"), không gắn `claude-sonnet-5-5-high` cho Planner; `run-agy.sh` không có biến model. | **FAIL** | Định nghĩa biến môi trường model ID chuẩn trong `run-agy.sh` và cập nhật tài liệu `AGENTS.md`, `PLANNER.md`, `REVIEWER.md`, `ROADMAP.md`. |
| **REQ-MOD-02** | Cơ chế gọi CLI thực tế (không chỉ ghi trong Markdown), truyền prompt, thu output, exit code, timeout. | `run-agy.sh`, `reports/agy-cli-capability-matrix.md` | `run-agy.sh:81-84` chặn lệnh `run` (`exit 20`). `agy-cli-capability-matrix.md` đang `NOT_VERIFIED`. | **FAIL** | Viết hàm `invoke_agy_model()` gọi `agy --model "$model" --print --print-timeout "$timeout"` và thu thập stdout, stderr, exit code. |
| **REQ-MOD-03** | Không tự đổi model âm thầm khi hết quota; chính sách retry/fallback có ghi log; phân biệt lỗi quota, mạng, CLI, timeout, nội dung. | `AGENTS.md`, `REVIEWER.md`, `run-agy.sh` | `AGENTS.md:41` và `REVIEWER.md:67` cấm đổi model âm thầm trên lý thuyết, nhưng `run-agy.sh` chưa có bộ phân loại mã lỗi/stderr hay cơ chế fallback logging. | **FAIL** | Cài đặt hàm `classify_agy_error()` phân tách `QUOTA_EXCEEDED`, `NETWORK_ERROR`, `CLI_USAGE_ERROR`, `TIMEOUT_ERROR`, `CONTENT_INVALID` và ghi log fallback tường minh. |
| **REQ-TEST-01** | Không coi output AI tự tuyên bố `PASS` là bằng chứng; `AUTOMATED TESTER` phải chạy lệnh thật và ghi exit code. | `AGENTS.md`, `skills/testing-quality/SKILL.md`, `run-agy.sh` | Quy định rõ tại `AGENTS.md:37, 72` và `testing-quality/SKILL.md:4-6`, nhưng `run-agy.sh` chưa có hàm chạy `test_commands`. | **PARTIAL** | Cài đặt hàm `run_task_tests()` thực thi từng lệnh kiểm thử trong subshell cô lập, lưu stdout/stderr và kiểm tra `exit_code == 0`. |
| **REQ-GATE-01** | Test FAIL không gọi Reviewer; chỉ khi Test PASS mới gọi Reviewer. | `AGENTS.md`, `REVIEWER.md`, `skills/testing-quality/SKILL.md`, `run-agy.sh` | `AGENTS.md:52-53` và `run-agy.sh:50` có nêu nguyên tắc, nhưng chưa có nhánh điều kiện `if [[ $test_rc -ne 0 ]]` thật trong `run-agy.sh`. | **PARTIAL** | Cưỡng chế bằng code trong `run-agy.sh`: nếu `run_task_tests` trả về `!= 0`, bỏ qua `invoke_reviewer` và chuyển thẳng sang trạng thái sửa/lỗi. |
| **REQ-GATE-02** | Reviewer FAIL kích hoạt FIX LOOP → sửa code → chạy lại toàn bộ test bắt buộc → review lại; tối đa 3 vòng, quá 3 vòng chuyển `BLOCKED`. | `AGENTS.md`, `REVIEWER.md`, `run-agy.sh` | `run-agy.sh:16` có `MAX_REVIEW_ROUNDS=3`, `AGENTS.md:55` quy định tối đa 3 vòng, nhưng `REVIEWER.md:4` lại chỉ cho review cấp Feature thay vì Task, và `run-agy.sh` chưa có vòng lặp `for ((round=1; round<=MAX_REVIEW_ROUNDS; round++))`. | **PARTIAL** | Đồng bộ `REVIEWER.md` cho phép review từng Task và lập trình vòng lặp `FIX_LOOP` tối đa `MAX_REVIEW_ROUNDS=3` trong `run-agy.sh`. |
| **REQ-GATE-03** | Lưu log, kết quả test, model sử dụng, thời gian chạy, trạng thái task, lý do fallback; chống trạng thái `DONE` giả và log cũ. | `PROGRESS.md`, `templates/*.md`, `run-agy.sh` | `templates/*.md` có trường lưu thông tin, nhưng `run-agy.sh` chưa gắn `RUN_ID` / `SESSION_ID`, timestamp hoặc kiểm tra tệp bằng chứng thuộc phiên hiện tại trước khi set `DONE`. | **FAIL** | Tạo `RUN_ID` duy nhất cho mỗi lần chạy task; hàm `verify_done_gate()` phải đối chiếu `RUN_ID`, `test_exit_code=0` và `reviewer_verdict=PASS` trong file report mới cho phép ghi `DONE`. |
| **REQ-RES-01** | Chạy từng task tuần tự; mặc định không chạy song song nhiều agent/task. | `run-agy.sh` | `run-agy.sh:66-73` có `acquire_lock()` bằng `mkdir "$LOCK_DIR"`, nhưng chưa có vòng lặp chạy task tuần tự và chưa kiểm tra stale PID. | **PARTIAL** | Giữ cơ chế single-process lock (bổ sung kiểm tra `kill -0 "$old_pid"`) và thực thi tuần tự từng task một. |
| **REQ-RES-02** | Có thời gian nghỉ giữa hai task thành công (`TASK_COOLDOWN_SECONDS`) và sau lỗi liên tiếp (`ERROR_COOLDOWN_SECONDS`, `MAX_CONSECUTIVE_FAILURES`). | `run-agy.sh` | Kiểm tra toàn bộ 90 dòng `run-agy.sh`: **không tồn tại** biến hoặc lệnh `sleep` nào cho cooldown. | **FAIL** | Bổ sung `TASK_COOLDOWN_SECONDS=30`, `ERROR_COOLDOWN_SECONDS=120`, `MAX_CONSECUTIVE_FAILURES=3` cùng hàm `cooldown_sleep()` có kiểm tra `STOP_FILE`. |
| **REQ-RES-03** | Có timeout riêng cho mỗi lời gọi model và cho toàn bộ task (`TASK_TIMEOUT_SECONDS`), giới hạn số task (`MAX_TASKS_PER_SESSION`) và thời gian phiên (`SESSION_TIMEOUT_SECONDS`). | `run-agy.sh` | `run-agy.sh` không có bất kỳ biến timeout hoặc giới hạn phiên nào. | **FAIL** | Bổ sung `--print-timeout` cho `agy`, watchdog timer cho `TASK_TIMEOUT_SECONDS=900`, `SESSION_TIMEOUT_SECONDS=14400`, và `MAX_TASKS_PER_SESSION=5`. |
| **REQ-RES-04** | Kiểm tra CPU load (`CPU_LOAD_PER_CORE_LIMIT`) và bộ nhớ khả dụng (`MIN_FREE_MEMORY_PERCENT`) tương thích macOS trước khi bắt đầu task mới. | `run-agy.sh` | `run-agy.sh` hoàn toàn không kiểm tra CPU hay RAM. | **FAIL** | Bổ sung hàm `check_system_resources()` dùng `sysctl -n hw.ncpu vm.loadavg` và `vm_stat` / `sysctl -n hw.memsize` trên macOS (và `/proc/loadavg`, `/proc/meminfo` trên Linux). |
| **REQ-RES-05** | Khi CPU/RAM vượt ngưỡng, chờ theo chu kỳ giới hạn (`RESOURCE_POLL_INTERVAL_SECONDS`, `RESOURCE_WAIT_SECONDS`); quá hạn thì chuyển `BLOCKED` hoặc dừng an toàn. | `run-agy.sh` | Không tồn tại trong `run-agy.sh`. | **FAIL** | Cài đặt hàm `wait_for_resources()` với `RESOURCE_POLL_INTERVAL_SECONDS=15`, `RESOURCE_WAIT_SECONDS=300`; nếu hết thời gian chờ mà vẫn quá tải thì ghi `BLOCKED (RESOURCE_EXHAUSTED)` và thoát an toàn. |
| **REQ-RES-06** | Hỗ trợ dừng thủ công (`STOP_FILE`), deadline/giờ dừng (`STOP_AT_HOUR_FILE`), xử lý tiến trình treo, tín hiệu `INT`/`TERM` và dọn lock an toàn. | `run-agy.sh` | `run-agy.sh:14-15` khai báo `STOP_FILE` và `STOP_AT_HOUR_FILE`, nhưng `STOP_AT_HOUR_FILE` không được đọc ở bất kỳ đâu; `trap` ở dòng 72 chỉ bắt `EXIT`, chưa bắt `INT`/`TERM` để giết tiến trình con và trả mã thoát chuẩn. | **PARTIAL** | Cài đặt hàm `should_stop()` kiểm tra cả `STOP_FILE`, `STOP_AT_HOUR_FILE` và `SESSION_TIMEOUT_SECONDS`; bổ sung `trap cleanup_and_exit INT TERM EXIT` quản lý cả child PID. |
| **REQ-RES-07** | Giới hạn retry, exponential backoff + jitter; `QUOTA_BACKOFF_SECONDS` dài hơn cooldown thường và có ghi log; không xóa/stash/reset file người dùng khi hết quota. | `run-agy.sh` | Không tồn tại logic retry/backoff/quota trong `run-agy.sh`. | **FAIL** | Bổ sung `QUOTA_BACKOFF_SECONDS=900`, backoff mũ `base * 2^(attempt-1) + jitter`, giữ nguyên working tree khi dừng vì hết quota. |
| **REQ-GIT-01** | Kiểm tra branch và working tree trước khi chạy; nếu working tree bẩn không rõ nguồn gốc thì dừng an toàn; cấm `git reset --hard`, `git clean`, `stash`, force push. | `AGENTS.md`, `run-agy.sh` | `AGENTS.md:76-80` có quy định đầy đủ, nhưng `run-agy.sh` không chạy `git status --porcelain` hay kiểm tra branch trước khi vào luồng thực thi. | **PARTIAL** | Bổ sung hàm `assert_clean_git_tree()` trong `run-agy.sh`: kiểm tra `git status --porcelain` tại `PROJECT_ROOT`; nếu bẩn thì dừng ngay với mã lỗi và thông báo rõ danh sách file. |
| **REQ-SEC-01** | Bảo vệ database `DBQLPK`, không tự chạy migration phá hủy, không lộ secret/password/token từ `.env` vào log hoặc báo cáo. | `AGENTS.md`, `skills/database-migrations/SKILL.md`, `skills/security-privacy/SKILL.md`, `run-agy.sh` | Quy định tốt trong các file Markdown và `be/phpunit.xml` dùng SQLite `:memory:`, nhưng `run-agy.sh:35` (`log()`) chưa có bộ lọc redact secret và chưa chặn lệnh `migrate:fresh`/`db:wipe`. | **PARTIAL** | Thêm bộ lọc `redact_secrets()` vào hàm `log()` của `run-agy.sh` và danh sách đen các lệnh phá hủy DB/Git trong bộ chạy test/task. |
| **REQ-PATH-01** | Phân biệt rõ `FRAMEWORK_DIR` (`qlpk-autonomous-framework/`) và `PROJECT_ROOT` (`clinic-management-system/`); chạy đúng từ bất kỳ thư mục terminal nào; không tạo thư mục rác khi chỉ chạy lệnh thông tin. | `README.md`, `run-agy.sh`, `ROADMAP.md` | `run-agy.sh:10` đặt `ROOT_DIR` là thư mục chứa script, không có `PROJECT_ROOT`; dòng 18 tạo `.agy-state` và `logs` ngay cả khi gọi `./run-agy.sh status`. | **FAIL** | Tách `FRAMEWORK_DIR` và `PROJECT_ROOT="$(cd -- "${FRAMEWORK_DIR}/.." && pwd)"`; chỉ tạo `STATE_DIR`/`LOG_DIR` khi thực sự chạy lệnh ghi trạng thái. |

---

## D. Model Routing Audit (Kiểm toán định tuyến Model & AGY CLI)

### 1. Bằng chứng thực nghiệm từ `agy` CLI (v1.4.7) trên máy
Đã kiểm tra thực tế bằng các lệnh an toàn `agy --version`, `agy --help`, `agy models`, `agy agents`:

- **Phiên bản:** `1.4.7`
- **Các cờ CLI chính thức đã xác minh từ `agy --help`:**
  - Chọn model: `--model <model-id>`
  - Chạy non-interactive (in kết quả rồi thoát): `-p` / `--print` / `--prompt`
  - Giới hạn thời gian chạy ở chế độ print: `--print-timeout <duration>` (mặc định `0s` là chờ vô hạn — **rủi ro treo nếu không truyền tham số này!**)
  - Định dạng đầu ra: `--output-format <text|json|stream-json>` (mặc định `text`)
  - Ép cấu trúc đầu ra JSON: `--json-schema <schema-string-or-file>`
  - Mức độ suy luận: `--effort <low|medium|high|xhigh|max>`
  - Chế độ thực thi: `--mode <accept-edits|auto|plan>`
  - Giới hạn sandbox: `--sandbox` (chạy trong sandbox hạn chế terminal)
  - Thêm thư mục vào workspace: `--add-dir <dir>`
  - Ghi log CLI riêng: `--log-file <path>`
  - **Cờ nguy hiểm KHÔNG ĐƯỢC BẬT mặc định:** `--dangerously-skip-permissions`
- **Danh sách 13 model ID hợp lệ từ `agy models`:**
  - `gemini-4-argon-medium`, `gemini-4-argon-high`, `gemini-4-argon-low`
  - `gemini-3.8-flash-high`, `gemini-3.8-flash-medium`, `gemini-3.8-flash-low`
  - `claude-opus-5-5-low`, `claude-opus-5-5-medium`, `claude-opus-5-5-high`
  - `claude-sonnet-5-5-low`, `claude-sonnet-5-5-medium`, `claude-sonnet-5-5-high`
  - `gpt-oss-120b-medium`
- **Danh sách agent từ `agy agents`:** Trả về rỗng (`exit_code=0`), nghĩa là hệ thống chưa định nghĩa custom CLI sub-agent riêng và việc phân vai trò cần được điều phối qua `--model`, `--mode`, `--json-schema` và system/role prompt từ `run-agy.sh`.

### 2. Đối chiếu Cấu hình Mục tiêu vs Tài liệu Markdown vs Lời gọi CLI Thực tế

| Vai trò (Role) | Model ID mục tiêu (Đã xác minh trong `agy models`) | Ghi nhận trong tài liệu Markdown hiện tại | Lời gọi CLI thực tế trong `run-agy.sh` | Trạng thái Audit |
|---|---|---|---|---|
| **PLANNER** | `claude-sonnet-5-5-high` | `AGENTS.md:35` & `PLANNER.md:19`: chỉ ghi *"Dùng model mạnh khi dự án/feature có độ phức tạp đáng kể"*, không ghi tên model. | **Không có** (chưa cài đặt). | **FAIL** |
| **CODER** | `gemini-3.8-flash-high` | `AGENTS.md:33,36` & `ROADMAP.md:23`: chỉ ghi *"Gemini 3.8 Flash"*, thiếu hậu tố `-high`. | **Không có** (chưa cài đặt). | **FAIL** |
| **AUTOMATED TESTER** | *Không dùng LLM* (Chạy shell thật) | `AGENTS.md:37`: *"script chạy lệnh thật, thu exit code và log"*. | **Không có** hàm chạy test trong `run-agy.sh`. | **PARTIAL** |
| **REVIEWER** | `gemini-4-argon-high` | `AGENTS.md:38` & `REVIEWER.md:4`: chỉ ghi *"Gemini 4 Argon"*, thiếu hậu tố `-high`. | **Không có** (chưa cài đặt). | **FAIL** |
| **DEBUG FALLBACK** | `claude-sonnet-5-5-high` | `AGENTS.md:39` & `REVIEWER.md:65`: ghi *"Claude Sonnet 5.5"*, thiếu hậu tố `-high` và chỉ mô tả cho Reviewer. | **Không có** (chưa cài đặt). | **FAIL** |
| **ARCH / SECURITY ESCALATION** | `claude-opus-5-5-high` | `AGENTS.md:39` & `REVIEWER.md:66`: ghi *"Claude Opus 5.5"*, thiếu hậu tố `-high`. | **Không có** (chưa cài đặt). | **FAIL** |

### 3. Thiết kế chuẩn lời gọi CLI và Phân loại lỗi cần bổ sung vào `run-agy.sh`
- **Cú pháp gọi chuẩn cho từng vai trò:**
  - **PLANNER / REVIEWER (Read-Only):**
    ```bash
    agy --model "$ROLE_MODEL" \
        --mode plan \
        --sandbox \
        --disable-slash-commands \
        --output-format json \
        --print-timeout "${MODEL_TIMEOUT_SECONDS}s" \
        --log-file "$call_log_file" \
        --print "$prompt_payload"
    ```
  - **CODER (Controlled Write trong phạm vi task):**
    ```bash
    agy --model "$CODER_MODEL" \
        --mode accept-edits \
        --add-dir "$PROJECT_ROOT" \
        --disable-slash-commands \
        --output-format json \
        --print-timeout "${MODEL_TIMEOUT_SECONDS}s" \
        --log-file "$call_log_file" \
        --print "$prompt_payload"
    ```
- **Phân loại 5 nhóm lỗi bắt buộc từ exit code + stderr/stdout:**
  1. `TIMEOUT_ERROR`: tiến trình bị dừng bởi watchdog (`124`/`137`) hoặc `agy --print-timeout` báo hết thời gian chờ.
  2. `QUOTA_ERROR`: stderr/stdout chứa tín hiệu `429`, `RESOURCE_EXHAUSTED`, `rate limit`, `quota exceeded`, `insufficient_quota`. **Hành động:** Chờ `QUOTA_BACKOFF_SECONDS` (mặc định `900s`), ghi log rõ ràng, **tuyệt đối không tự ý hạ cấp/đổi model âm thầm**.
  3. `NETWORK_ERROR`: `ECONNRESET`, `ETIMEDOUT`, `502`, `503`, `504`, `connection refused`, `DNS`. **Hành động:** Retry với exponential backoff + jitter tối đa `MAX_MODEL_RETRIES` lần.
  4. `CLI_ERROR`: `unknown flag`, `invalid model`, `authentication failed`, exit code `2`. **Hành động:** Dừng ngay (`BLOCKED`), không retry vô ích.
  5. `CONTENT_ERROR`: JSON trả về rỗng, thiếu trường bắt buộc của schema (ví dụ Reviewer không trả về `PASS|FAIL|BLOCKED` hoặc thiếu `finding_id`). **Hành động:** Yêu cầu chạy lại hoặc kích hoạt Fallback có ghi log nếu được cấu hình.

---

## E. CPU/RAM and Cooldown Audit (Kiểm toán Tài nguyên, Cooldown & Timeout)

### 1. Hiện trạng trong `run-agy.sh`
Qua kiểm tra toàn bộ 90 dòng của `qlpk-autonomous-framework/run-agy.sh`, chỉ có duy nhất biến `MAX_REVIEW_ROUNDS="${MAX_REVIEW_ROUNDS:-3}"` (dòng 16) và hai đường dẫn `STOP_FILE` / `STOP_AT_HOUR_FILE` (dòng 14–15, trong đó `STOP_AT_HOUR_FILE` hoàn toàn không được sử dụng). **Mọi cơ chế kiểm soát CPU, RAM, Cooldown, Timeout, Backoff đều chưa tồn tại (`FAIL`).**

### 2. Phân tích thực tế tài nguyên máy chủ hiện tại (macOS)
Kết quả đo thực nghiệm trên máy của bạn trong quá trình audit:
- **Số nhân CPU logic (`sysctl -n hw.ncpu`):** `4` cores.
- **Load Average hiện tại (`sysctl -n vm.loadavg`):** `{ 6.33 4.37 4.05 }` → Tải trung bình 1 phút là **`6.33`**, tương đương **`1.5825` load/core** (158.25% công suất định danh).
- **Tổng RAM (`sysctl -n hw.memsize`):** `17,179,869,184` bytes (`16 GB`).
- **Thống kê trang nhớ (`vm_stat`, page size = `4096` bytes):**
  - `Pages free`: `83,089` (~324 MB)
  - `Pages inactive`: `1,224,328` (~4,782 MB)
  - `Pages speculative`: `6,172` (~24 MB)
  - **Lưu ý kỹ thuật cực kỳ quan trọng trên macOS:** Nếu script chỉ lấy `Pages free` (`~324 MB / 16 GB = 2.0%`), máy Mac sẽ luôn bị coi là hết RAM dù thực tế hệ điều hành giữ bộ nhớ đệm trong `inactive` + `speculative` (tổng bộ nhớ khả dụng thực tế là `(free + inactive + speculative) * page_size ≈ 5.13 GB`, tức **`32.06%`**). Do đó, công thức tính RAM khả dụng trên macOS bắt buộc phải tính `(Pages free + Pages inactive + Pages speculative) * page_size / hw.memsize * 100`.

### 3. Bảng thông số kiểm soát tài nguyên và thời gian nghỉ cần thiết lập

| Biến cấu hình | Giá trị mặc định đề xuất | Hiện trạng trong `run-agy.sh` | Cách tính & Cơ chế vận hành chuẩn trên macOS / Linux |
|---|---|---|---|
| `CPU_LOAD_PER_CORE_LIMIT` | `0.85` (hoặc `1.20` khi máy chạy IDE) | **MISSING (`FAIL`)** | Lấy `load_1m` từ `sysctl -n vm.loadavg` (macOS) hoặc `/proc/loadavg` (Linux) chia cho số logical CPU (`sysctl -n hw.ncpu` hoặc `nproc`). Chỉ khởi chạy task mới khi `load_per_core <= CPU_LOAD_PER_CORE_LIMIT`. |
| `MIN_FREE_MEMORY_PERCENT` | `15` (%) | **MISSING (`FAIL`)** | Trên macOS: `avail_pages = free + inactive + speculative` từ `vm_stat`, nhân `page_size`, chia `sysctl -n hw.memsize`. Trên Linux: `MemAvailable / MemTotal` từ `/proc/meminfo`. Chỉ chạy task khi `avail_pct >= MIN_FREE_MEMORY_PERCENT`. |
| `RESOURCE_POLL_INTERVAL_SECONDS` | `15` (giây) | **MISSING (`FAIL`)** | Chu kỳ ngủ giữa 2 lần kiểm tra lại CPU/RAM khi máy đang vượt ngưỡng. Tuyệt đối không dùng vòng lặp `while` không có `sleep` hoặc `sleep 1` gây tốn CPU. |
| `RESOURCE_WAIT_SECONDS` | `300` (5 phút) | **MISSING (`FAIL`)** | Tổng thời gian tối đa chờ CPU/RAM hạ nhiệt trước một task. Nếu `waited >= RESOURCE_WAIT_SECONDS` mà tài nguyên chưa hồi phục, ghi trạng thái `BLOCKED (RESOURCE_OVERLOAD)` và dừng an toàn. |
| `TASK_COOLDOWN_SECONDS` | `30` (giây) | **MISSING (`FAIL`)** | Thời gian nghỉ bắt buộc sau khi một task hoàn thành (`DONE`) trước khi chuyển sang task kế tiếp. Trong khi chờ vẫn kiểm tra `STOP_FILE` mỗi giây hoặc theo bước nhỏ. |
| `ERROR_COOLDOWN_SECONDS` | `120` (2 phút) | **MISSING (`FAIL`)** | Thời gian nghỉ dài hơn sau khi một task hoặc lời gọi model gặp lỗi trước khi thử lại hoặc chuyển task. |
| `QUOTA_BACKOFF_SECONDS` | `900` (15 phút) | **MISSING (`FAIL`)** | Thời gian nghỉ bắt buộc khi phát hiện lỗi hết quota/rate-limit (`429` / `RESOURCE_EXHAUSTED`). Phải dài hơn `ERROR_COOLDOWN_SECONDS` và ghi log cảnh báo rõ ràng. |
| `MODEL_TIMEOUT_SECONDS` | `300` (5 phút) | **MISSING (`FAIL`)** | Truyền trực tiếp vào `agy --print-timeout "${MODEL_TIMEOUT_SECONDS}s"` và bọc ngoài bằng watchdog timer để tránh treo tiến trình con. |
| `TASK_TIMEOUT_SECONDS` | `900` (15 phút) | **MISSING (`FAIL`)** | Tổng thời gian tối đa cho toàn bộ 1 task (bao gồm Coder + Tester + Reviewer + Fix Loop). Quá hạn sẽ gửi `SIGTERM` → chờ 5s → `SIGKILL` cho tiến trình con và đánh dấu task `BLOCKED (TASK_TIMEOUT)`. |
| `SESSION_TIMEOUT_SECONDS` | `14400` (4 giờ) | **MISSING (`FAIL`)** | Tổng thời gian tối đa của một phiên chạy `./run-agy.sh run`. Kiểm tra trước mỗi task và trước mỗi vòng review. |
| `MAX_TASKS_PER_SESSION` | `5` (tasks) | **MISSING (`FAIL`)** | Số lượng task tối đa được phép chạy trong một phiên thực thi tự động. |
| `MAX_CONSECUTIVE_FAILURES` | `2` (lần) | **MISSING (`FAIL`)** | Nếu có `N` task liên tiếp thất bại hoặc bị `BLOCKED`, dừng toàn bộ phiên chạy để tránh đốt quota vô ích. |
| `MAX_REVIEW_ROUNDS` | `3` (vòng) | **DECLARED (`PARTIAL`)** | Đã khai báo ở `run-agy.sh:16` nhưng chưa có vòng lặp cưỡng chế thực tế. |

### 4. Các tình huống có thể gây quá tải hoặc lặp vô hạn nếu không sửa
1. **Gọi `agy --print` với `--print-timeout` mặc định (`0s`):** Theo `agy --help`, `--print-timeout` mặc định là `0s` (*"0 waits until the turn completes"*). Nếu kết nối mạng bị treo hoặc model bị kẹt, tiến trình sẽ treo vĩnh viễn và giữ nguyên `.agy-state/run.lock`.
2. **Tiến trình bị giết bởi `SIGINT`/`SIGTERM` nhưng không thu hồi tiến trình con:** `run-agy.sh` hiện chỉ `trap ... EXIT` để xóa thư mục lock, chưa kill PID của tiến trình `agy` hoặc `phpunit`/`next build` đang chạy ngầm.
3. **Lock mồ côi (Stale Lock):** Nếu máy sập nguồn hoặc nhận `SIGKILL (-9)`, thư mục `.agy-state/run.lock` vẫn tồn tại và chặn mọi lần chạy sau, dù PID trong `.agy-state/run.lock/pid` không còn sống.

---

## F. Automated Test Matrix (Ma trận Kiểm thử An toàn — Giai đoạn 2)

Tuân thủ nghiêm ngặt quy tắc: **Chỉ ghi `PASS` cho bài kiểm thử đã thực thi thật và có bằng chứng; các kịch bản kiểm thử hành vi điều phối được thiết kế đầy đủ bằng Mock/Harness cô lập và ghi nhận trung thực là `NOT RUN` (do `run-agy.sh` hiện tại chưa có các hàm logic này).**

| Test ID | Điều kiện đầu vào (Input / Precondition) | Kết quả mong đợi (Expected Outcome) | Cách chạy (Isolated Command / Mock Harness) | Kết quả thực tế | Trạng thái | Bằng chứng & Log liên quan |
|---|---|---|---|---|---|---|
| **AT-00** | File `qlpk-autonomous-framework/run-agy.sh` hiện tại (90 dòng). | Cú pháp Bash hợp lệ, không có lỗi syntax, không làm thay đổi trạng thái thư mục. | `bash -n qlpk-autonomous-framework/run-agy.sh` | Exit code `0`, không có lỗi cú pháp. | **PASS** *(EXECUTED AND PASSED)* | Đã chạy thực tế trong phiên audit: `bash -n exit_code=0`. |
| **AT-01** | Mock `CODER` trả về exit code `0`, nhưng lệnh trong `test_commands` trả về exit code `1` (Test FAIL). | Không gọi `REVIEWER` (`reviewer_invocations == 0`); task chuyển sang sửa lỗi hoặc `FAIL`; không được đánh dấu `DONE`. | Chạy trong thư mục tạm `/tmp/qlpk-test-at01` với `MOCK_AGY_BIN` (ghi log mỗi lần được gọi) và `TEST_CMD="exit 1"`. Kiểm tra file `mock_agy_calls.log` không chứa `--model gemini-4-argon-high`. | Chưa chạy (do `run-agy.sh` chưa cài đặt test gate & reviewer gate). | **NOT RUN** | `run-agy.sh:81-84` hiện tại thoát ngay ở lệnh `run` với mã `20`. |
| **AT-02** | Mock `CODER` trả về `0` và toàn bộ `test_commands` trả về exit code `0` (Test PASS). | `REVIEWER` được gọi đúng 1 lần cho vòng review đầu tiên với model `gemini-4-argon-high`. | Chạy với `MOCK_AGY_BIN` và `TEST_CMD="exit 0"`. Kiểm tra `mock_agy_calls.log` ghi nhận đúng 1 lời gọi `CODER` (`gemini-3.8-flash-high`) theo sau bởi 1 lời gọi `REVIEWER` (`gemini-4-argon-high`). | Chưa chạy. | **NOT RUN** | Cần cài đặt `run_task_tests()` và `invoke_reviewer()` trước. |
| **AT-03** | Test PASS, nhưng Mock `REVIEWER` trả về `Overall result: FAIL` ở vòng 1, sau đó trả về `PASS` ở vòng 2. | Kích hoạt `FIX LOOP`: gọi `CODER` sửa lỗi → bắt buộc chạy lại `test_commands` (PASS) → gọi lại `REVIEWER` (vòng 2 PASS) → Task đạt `DONE`. | Cấu hình `MOCK_AGY_BIN` trả `FAIL` ở lần gọi Reviewer #1 và `PASS` ở lần gọi Reviewer #2. Kiểm tra thứ tự log: `CODER -> TEST -> REVIEW(1:FAIL) -> CODER(FIX) -> TEST(RE-RUN) -> REVIEW(2:PASS) -> DONE`. | Chưa chạy. | **NOT RUN** | Cần cài đặt vòng lặp `FIX_LOOP` trong `run-agy.sh`. |
| **AT-04** | Mock `REVIEWER` liên tục trả về `FAIL` trong cả 3 vòng (`MAX_REVIEW_ROUNDS=3`). | Sau vòng review thứ 3 thất bại, dừng vòng lặp ngay lập tức, đánh dấu task là `BLOCKED`, ghi rõ lý do `MAX_REVIEW_ROUNDS_EXCEEDED`, không gọi vòng thứ 4. | Cấu hình `MOCK_AGY_BIN` luôn trả về `FAIL` cho Reviewer, đặt `MAX_REVIEW_ROUNDS=3`. Kiểm tra tổng số lần gọi Reviewer bằng đúng `3` và exit status / task status là `BLOCKED`. | Chưa chạy. | **NOT RUN** | Biến `MAX_REVIEW_ROUNDS=3` đã có ở `run-agy.sh:16` nhưng chưa có vòng lặp thực thi. |
| **AT-05** | Ngưỡng CPU hoặc RAM bị vượt (`CPU_LOAD_PER_CORE_LIMIT=0.01` hoặc `MIN_FREE_MEMORY_PERCENT=99`, `RESOURCE_WAIT_SECONDS=4`, `RESOURCE_POLL_INTERVAL_SECONDS=2`). | Không khởi chạy task mới; chờ đúng 2 chu kỳ (`2s * 2 = 4s`), sau đó chuyển trạng thái `BLOCKED (RESOURCE_OVERLOAD)` và thoát an toàn; `MOCK_AGY_BIN` không được gọi lần nào. | Chạy `CPU_LOAD_PER_CORE_LIMIT=0.01 RESOURCE_WAIT_SECONDS=4 RESOURCE_POLL_INTERVAL_SECONDS=2 ./run-agy.sh run` với mock binary. | Chưa chạy. | **NOT RUN** | Cần cài đặt `check_system_resources()` và `wait_for_resources()`. |
| **AT-06** | Có 2 task liên tiếp trong danh sách (`T-MOCK-001`, `T-MOCK-002`), cấu hình `TASK_COOLDOWN_SECONDS=3`. | Sau khi `T-MOCK-001` hoàn tất, orchestrator nghỉ ít nhất `3` giây trước khi bắt đầu `T-MOCK-002` và ghi log `[COOLDOWN] Sleeping 3s between tasks`. | Chạy 2 mock task với `TASK_COOLDOWN_SECONDS=3`, đo chênh lệch timestamp giữa `END T-MOCK-001` và `START T-MOCK-002` trong `orchestrator.log` (`delta >= 3s`). | Chưa chạy. | **NOT RUN** | Cần cài đặt `TASK_COOLDOWN_SECONDS` trong `run-agy.sh`. |
| **AT-07** | Tiến trình model bị treo quá `TASK_TIMEOUT_SECONDS=3` (mock script chạy `sleep 30`). | Watchdog cắt tiến trình sau ~3 giây, gửi `SIGTERM`/`SIGKILL` cho tiến trình con, xóa `.agy-state/run.lock` sạch sẽ và ghi log `TIMEOUT_ERROR`. | Cấu hình `MOCK_AGY_BIN` thực thi `sleep 30`, chạy với `MODEL_TIMEOUT_SECONDS=3`. Kiểm tra thời gian chạy `< 6s`, `! -d .agy-state/run.lock` và tiến trình `sleep 30` không còn tồn tại. | Chưa chạy. | **NOT RUN** | Cần cài đặt watchdog timeout và process group cleanup trong `run-agy.sh`. |
| **AT-08** | Đang có file chưa commit trong working tree của task thì Mock `agy` trả về lỗi `429 RESOURCE_EXHAUSTED` (hết quota). | Orchestrator ghi log `QUOTA_EXCEEDED`, chờ `QUOTA_BACKOFF_SECONDS` (hoặc dừng an toàn), **giữ nguyên 100% nội dung các file đã sửa trong working tree**, không chạy `git reset`, `git checkout`, `git stash` hay `git clean`. | Tạo thay đổi giả lập trên file được phép trong repo test tạm, kích hoạt lỗi `429` từ `MOCK_AGY_BIN` với `QUOTA_BACKOFF_SECONDS=2 MAX_MODEL_RETRIES=1`. Kiểm tra `git diff` trước và sau khi lỗi quota là giống hệt nhau. | Chưa chạy. | **NOT RUN** | Cần cài đặt `classify_agy_error()` và chính sách bảo toàn working tree khi hết quota. |
| **AT-09** | Working tree có thay đổi lạ không rõ nguồn gốc trước khi khởi chạy `./run-agy.sh run`. | Orchestrator phát hiện working tree bẩn qua `git status --porcelain`, từ chối chạy ngay lập tức với thông báo lỗi rõ ràng, không xóa/stash file của người dùng và không gọi bất kỳ model nào. | Tạo một file untracked/modified trong repo test tạm, gọi `./run-agy.sh run`. Kiểm tra exit code khác `0`, file vẫn còn nguyên và `mock_agy_calls.log` rỗng. | Chưa chạy. | **NOT RUN** | Cần cài đặt `assert_clean_git_tree()` trong `run-agy.sh`. |
| **AT-10** | Model chính gặp lỗi cho phép fallback (hoặc Reviewer yêu cầu escalation sang `claude-sonnet-5-5-high`). | Không đổi model âm thầm; nếu chính sách cho phép fallback thì phải ghi rõ vào `orchestrator.log` và report: model gốc, mã lỗi/lý do trigger, model fallback và kết quả. | Giả lập lỗi cần fallback trên `MOCK_AGY_BIN`, kiểm tra `orchestrator.log` có dòng `FALLBACK_TRIGGERED: from=... to=claude-sonnet-5-5-high reason=...`. | Chưa chạy. | **NOT RUN** | Cần cài đặt cơ chế logging fallback tường minh trong `run-agy.sh`. |
| **AT-11** | Có sẵn file log/report cũ từ phiên chạy trước (`RUN_ID` cũ) hoặc Coder tự ghi chữ `DONE` vào `PROGRESS.md` khi chưa có report test `PASS` và review `PASS` của `RUN_ID` hiện tại. | Hàm `verify_done_gate()` phát hiện thiếu bằng chứng hợp lệ của `RUN_ID` hiện tại, từ chối trạng thái `DONE`, giữ trạng thái `FAIL`/`BLOCKED` và ghi cảnh báo `FAKE_OR_STALE_DONE_REJECTED`. | Đặt sẵn file report có `RUN_ID=old-run-123` rồi gọi hàm kiểm tra nghiệm thu với `RUN_ID=new-run-456`. Kiểm tra hàm trả về mã lỗi `!= 0`. | Chưa chạy. | **NOT RUN** | Cần cài đặt `verify_done_gate()` đối chiếu `RUN_ID`, `test_exit_code=0` và `reviewer_verdict=PASS`. |

---

## G. Security and Data Protection (Bảo mật & Bảo vệ Dữ liệu)

### 1. Nguy cơ mất dữ liệu Git & Working Tree
- **Hiện trạng Git của dự án (`git status --short --branch`):**
  ```text
  ## main...origin/main
  ?? qlpk-autonomous-framework-v2.zip
  ?? qlpk-autonomous-framework/
  ```
- **Rủi ro phát hiện:**
  1. `AGENTS.md:76` quy định *"Làm việc trên nhánh `dev`"*, nhưng repository thực tế đang ở nhánh `main`. Nếu agent tự động chuyển nhánh hoặc tạo nhánh mà không kiểm tra trước sẽ gây xung đột.
  2. `run-agy.sh` hiện chưa có cổng kiểm tra `git status --porcelain` trước khi chạy task và chưa có cơ chế kiểm tra `git diff --name-only` sau khi Coder chạy xong để đảm bảo Coder chỉ sửa các file nằm trong `allowed_files`.
  3. **Biện pháp bắt buộc:** Tuyệt đối cấm `git reset --hard`, `git clean`, `git checkout -- .`, `git stash` tự động trong `run-agy.sh`. Trước khi chạy task phải lưu danh sách file baseline; nếu working tree có thay đổi không thuộc task hiện tại thì dừng an toàn (`BLOCKED: DIRTY_WORKING_TREE`).

### 2. Quyền chạy Shell & Sandbox của AGY CLI
- `agy` `1.4.7` có cờ `--dangerously-skip-permissions`. **Tuyệt đối không được sử dụng cờ này mặc định** trong `run-agy.sh`.
- Với các vai trò chỉ đọc (`PLANNER`, `REVIEWER`), bắt buộc truyền `--mode plan --sandbox` để khóa quyền ghi file và hạn chế lệnh hệ thống.
- Với vai trò `CODER`, chỉ cấp quyền trong phạm vi thư mục dự án (`--mode accept-edits --add-dir "$PROJECT_ROOT"`) kèm bước hậu kiểm tra `git diff --name-only` đối chiếu với `allowed_files` của task.

### 3. Bảo vệ Database `DBQLPK`
- **Bằng chứng cấu hình hiện tại:**
  - Trong `be/.env` (dòng 23–28): `DB_CONNECTION=mysql`, `DB_DATABASE=DBQLPK`, `DB_USERNAME=root`, `DB_PASSWORD=123456`.
  - Trong `be/phpunit.xml` (dòng 26–27): `<env name="DB_CONNECTION" value="sqlite"/>` và `<env name="DB_DATABASE" value=":memory:"/>`.
- **Đánh giá rủi ro:** Khi chạy `php artisan test` hoặc `./vendor/bin/phpunit`, PHPUnit sẽ dùng SQLite `:memory:` (an toàn cho `DBQLPK`). Tuy nhiên, nếu Coder agent tự ý chạy các lệnh `php artisan migrate:fresh`, `php artisan migrate:refresh`, `php artisan migrate:reset`, hoặc `php artisan db:wipe` trên shell, Laravel sẽ đọc `be/.env` và **xóa toàn bộ bảng trong `DBQLPK`**.
- **Biện pháp bắt buộc:**
  1. Cài đặt bộ lọc lệnh cấm (`FORBIDDEN_COMMAND_PATTERNS`) trong `run-agy.sh` chặn mọi lệnh chứa `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `db:wipe`, `DROP DATABASE`, `DROP TABLE`, `TRUNCATE`, `rm -rf`, `git reset --hard`, `git clean`, `push --force`.
  2. Mọi thao tác migration lên `DBQLPK` phải có cờ phê duyệt rõ ràng từ người dùng trước khi chạy.

### 4. Bảo vệ Secret & Thông tin nhạy cảm
- `be/.env` hiện chứa mật khẩu database dạng cleartext (`DB_PASSWORD=123456`) và `APP_KEY`.
- Hàm `log()` tại `run-agy.sh:35` hiện ghi thẳng chuỗi ra `logs/orchestrator.log` mà chưa có bước lọc (redact).
- **Biện pháp bắt buộc:** Mọi đầu ra log và report phải đi qua hàm `redact_secrets()` (che các mẫu `DB_PASSWORD=*`, `APP_KEY=*`, `Bearer *`, `password=*`, `token=*`, `secret=*`) và cấm đọc file `be/.env` đưa vào prompt gửi cho model.

---

## H. Prioritized Fix Plan (Kế hoạch Khắc phục theo Độ ưu tiên)

> **Lưu ý:** Theo đúng quy trình Giai đoạn 3, dưới đây là kế hoạch sửa đổi tối thiểu, cụ thể theo từng file và chưa tự ý chỉnh sửa vào các file hiện có khi chưa có phê duyệt của bạn.

### P0 — Bắt buộc sửa trước khi bật lệnh `run` cho bất kỳ task nào
1. **File `qlpk-autonomous-framework/reports/agy-cli-capability-matrix.md`**
   - **Nguyên nhân:** Toàn bộ 12 dòng đang ở trạng thái `Pending` / `NOT_VERIFIED`.
   - **Cách sửa:** Cập nhật kết quả xác minh thực tế từ `agy 1.4.7` (`--model`, `--print`, `--print-timeout`, `--output-format json`, `--json-schema`, `--effort`, `--mode`, `--sandbox`, danh sách 13 model ID).
   - **Tiêu chí xác nhận:** Không còn dòng `NOT_VERIFIED` nào cho các tính năng cốt lõi; có đầy đủ ngày giờ và lệnh kiểm chứng.
2. **File `qlpk-autonomous-framework/run-agy.sh` (Kiểm soát đường dẫn, Git, CPU/RAM, Timeout, Lock & Signal)**
   - **Nguyên nhân:** Chưa phân biệt `FRAMEWORK_DIR` và `PROJECT_ROOT`; tạo thư mục `.agy-state`/`logs` ngay cả khi chạy `status`; thiếu kiểm tra CPU/RAM, timeout, stale lock, trap `INT`/`TERM`, Git dirty check và secret redaction.
   - **Cách sửa:**
     - Tách `FRAMEWORK_DIR` và `PROJECT_ROOT="$(cd -- "${FRAMEWORK_DIR}/.." && pwd)"`.
     - Chuyển `mkdir -p "$STATE_DIR" "$LOG_DIR"` vào các lệnh thực thi thay vì chạy ở top-level khi gọi `status`/`help`.
     - Bổ sung các biến cấu hình tài nguyên (`CPU_LOAD_PER_CORE_LIMIT`, `MIN_FREE_MEMORY_PERCENT`, `RESOURCE_WAIT_SECONDS`, `RESOURCE_POLL_INTERVAL_SECONDS`, `TASK_COOLDOWN_SECONDS`, `ERROR_COOLDOWN_SECONDS`, `QUOTA_BACKOFF_SECONDS`, `MODEL_TIMEOUT_SECONDS`, `TASK_TIMEOUT_SECONDS`, `SESSION_TIMEOUT_SECONDS`, `MAX_TASKS_PER_SESSION`, `MAX_CONSECUTIVE_FAILURES`, `MAX_REVIEW_ROUNDS`).
     - Cài đặt `check_system_resources()` và `wait_for_resources()` tương thích macOS (`sysctl -n hw.ncpu vm.loadavg` và `vm_stat` + `sysctl -n hw.memsize`).
     - Cài đặt `assert_clean_git_tree()`, `redact_secrets()`, stale lock check (`kill -0`) và `trap cleanup_and_exit INT TERM EXIT`.
   - **Tiêu chí xác nhận:** Vượt qua các bài kiểm thử `AT-00`, `AT-05`, `AT-07`, `AT-09`.
3. **File `qlpk-autonomous-framework/run-agy.sh` (Model Routing, Test Gate, Reviewer Gate & Fix Loop)**
   - **Nguyên nhân:** Chưa có hàm gọi `agy` theo từng vai trò, chưa có hàm chạy test thật và cưỡng chế cổng `Test PASS -> Reviewer -> Fix Loop (max 3) -> Verify DONE`.
   - **Cách sửa:**
     - Khai báo rõ:
       - `PLANNER_MODEL="${PLANNER_MODEL:-claude-sonnet-5-5-high}"`
       - `CODER_MODEL="${CODER_MODEL:-gemini-3.8-flash-high}"`
       - `REVIEWER_MODEL="${REVIEWER_MODEL:-gemini-4-argon-high}"`
       - `DEBUG_FALLBACK_MODEL="${DEBUG_FALLBACK_MODEL:-claude-sonnet-5-5-high}"`
       - `ARCH_ESCALATION_MODEL="${ARCH_ESCALATION_MODEL:-claude-opus-5-5-high}"`
     - Cài đặt `invoke_agy_model()`, `classify_agy_error()`, `run_task_tests()`, `run_review_loop()` và `verify_done_gate()` (kiểm tra `RUN_ID`, `test_exit_code == 0`, `reviewer_verdict == PASS` và `allowed_files`).
   - **Tiêu chí xác nhận:** Vượt qua các bài kiểm thử `AT-01`, `AT-02`, `AT-03`, `AT-04`, `AT-08`, `AT-10`, `AT-11`.

### P1 — Bắt buộc sửa trước khi chạy tự động nhiều task liên tiếp
4. **Các file `AGENTS.md`, `PLANNER.md`, `REVIEWER.md`, `ROADMAP.md`, `PROGRESS.md`**
   - **Nguyên nhân:** Tên model chưa khớp ID chính xác của `agy models`; mâu thuẫn giữa review theo *Feature* (`REVIEWER.md:4`, `AGENTS.md:38`) với review theo *Task* (Mục 3 & 4B); `PROGRESS.md` để `Review status = NOT_APPLICABLE` cho các task và thiếu cột `Model`, `Duration`, `Fallback`, `Run ID`; `AGENTS.md` cố định nhánh `dev` trong khi repo dùng `main`.
   - **Cách sửa:**
     - Cập nhật đúng 5 model ID mục tiêu trong `AGENTS.md`, `PLANNER.md`, `REVIEWER.md`, `ROADMAP.md`.
     - Sửa `REVIEWER.md:4` và `AGENTS.md:45-58` để áp dụng cổng `CODER -> AUTOMATED TESTER -> REVIEWER -> FIX LOOP (0..3) -> DONE` cho từng Task (và giữ cổng tổng hợp ở cấp Feature nếu cần).
     - Cập nhật bảng trong `PROGRESS.md` và tách rõ `workdir` cho từng lệnh trong `ROADMAP.md`.
   - **Tiêu chí xác nhận:** Không còn mâu thuẫn giữa 5 file tài liệu và logic trong `run-agy.sh`; vượt qua bài kiểm thử `AT-06`.

### P2 — Nên cải thiện để vận hành thuận tiện hơn
5. **Các file `README.md`, `MORNING.md`, `reports/README.md`**
   - **Nguyên nhân:** `README.md` hướng dẫn copy đè ra project root; `MORNING.md` chưa thống kê chỉ số tài nguyên/cooldown/fallback; `reports/README.md` thiếu đường dẫn report review cấp task.
   - **Cách sửa:** Cập nhật `README.md` theo mô hình chạy từ thư mục con `qlpk-autonomous-framework/`; bổ sung mục thống kê Resource/Quota/Fallback vào `MORNING.md`; bổ sung mẫu tên file `reports/tasks/<task_id>-review-round-<n>.md` vào `reports/README.md`.
   - **Tiêu chí xác nhận:** Đường dẫn báo cáo nhất quán 100% giữa `run-agy.sh`, `reports/README.md` và `MORNING.md`.

### P3 — Cải tiến dài hạn
6. **Tách cấu hình task sang định dạng máy đọc (JSON/YAML manifest hoặc parser Markdown nghiêm ngặt) & JSON Schema cho Reviewer**
   - **Nguyên nhân:** Việc parse `ROADMAP.md` bằng regex shell dễ vỡ khi nội dung Markdown phức tạp.
   - **Cách sửa:** Sử dụng cờ `--json-schema` của `agy 1.4.7` để ép `REVIEWER` và `PLANNER` trả về JSON chuẩn (có `verdict: "PASS"|"FAIL"|"BLOCKED"`, mảng `findings`), giúp `run-agy.sh` kiểm tra cổng nghiệm thu chính xác tuyệt đối không phụ thuộc regex văn bản.
   - **Tiêu chí xác nhận:** `agy --output-format json --json-schema ...` trả về JSON hợp lệ được xác thực tự động.

---

## I. Final Decision (Quyết định Cuối cùng)

1. **Framework hiện có đáp ứng đầy đủ yêu cầu không?**
   - **KHÔNG.** Phần tài liệu quy chuẩn kỹ thuật (`skills/*.md`, `templates/*.md`) được viết tốt, nhưng phần thực thi cốt lõi (`run-agy.sh` và `reports/agy-cli-capability-matrix.md`) mới chỉ là khung scaffold rỗng, hoàn toàn thiếu bộ gọi model thực tế, thiếu cổng kiểm thử/review bằng code và thiếu toàn bộ cơ chế bảo vệ CPU/RAM/Cooldown/Timeout/Git.
2. **Có được phép chạy `run` chưa?**
   - **CHƯA ĐƯỢC PHÉP.** Lệnh `./run-agy.sh run` hiện đang bị khóa (`exit 20`) và **không được mở khóa** cho đến khi hoàn tất các mục sửa lỗi **P0**.
3. **Có được phép chạy nhiều task tự động chưa?**
   - **CHƯA ĐƯỢC PHÉP.** Cần hoàn tất cả **P0** và **P1**, đồng thời vượt qua bộ kiểm thử cô lập `AT-00` đến `AT-11` trước khi cho phép chạy nhiều task tuần tự.
4. **Cần sửa những file nào trước?**
   - **Ưu tiên 1 (P0):** `qlpk-autonomous-framework/reports/agy-cli-capability-matrix.md` và `qlpk-autonomous-framework/run-agy.sh`.
   - **Ưu tiên 2 (P1):** `qlpk-autonomous-framework/AGENTS.md`, `qlpk-autonomous-framework/PLANNER.md`, `qlpk-autonomous-framework/REVIEWER.md`, `qlpk-autonomous-framework/ROADMAP.md`, `qlpk-autonomous-framework/PROGRESS.md`.
   - **Ưu tiên 3 (P2):** `qlpk-autonomous-framework/README.md`, `qlpk-autonomous-framework/MORNING.md`, `qlpk-autonomous-framework/reports/README.md`.
5. **Cần những bài kiểm thử nào để chứng minh framework an toàn?**
   - Bắt buộc thực thi và đạt **PASS** toàn bộ 12 bài kiểm thử trong **Automated Test Matrix (`AT-00` → `AT-11`)** bằng Mock Binary (`MOCK_AGY_BIN`) trong môi trường cô lập (không tốn quota model thật, không chạm `DBQLPK`, không sửa code `be/` và `fe/`), bao gồm:
     - `AT-00`: Kiểm tra cú pháp `bash -n` (Đã **PASS**).
     - `AT-01` & `AT-02`: Cổng Test FAIL chặn Reviewer; Test PASS mới gọi Reviewer.
     - `AT-03` & `AT-04`: Vòng lặp Fix → Retest → Re-review và khóa `BLOCKED` sau 3 vòng.
     - `AT-05` & `AT-06`: Chặn chạy khi CPU/RAM vượt ngưỡng + nghỉ `TASK_COOLDOWN_SECONDS` giữa 2 task.
     - `AT-07`: Cắt tiến trình treo khi quá timeout và giải phóng lock sạch sẽ.
     - `AT-08` & `AT-09`: Bảo toàn 100% file khi hết quota và dừng an toàn khi working tree bẩn.
     - `AT-10` & `AT-11`: Ghi log tường minh khi fallback model và chặn trạng thái `DONE` giả / log cũ.


---

## J. Post-Remediation & Verification Addendum (`2026-10-26T17:14:26+07:00`)

Sau khi được người dùng phê duyệt (`oke duyệt`), các bản vá **P0, P1, P2** đã được áp dụng và xác minh thực nghiệm bằng bộ kiểm thử cô lập `./qlpk-autonomous-framework/run-agy.sh self-test`.

### 1. Các file đã được nâng cấp và đồng bộ
- `qlpk-autonomous-framework/run-agy.sh`: Cài đặt đầy đủ tách đường dẫn `FRAMEWORK_DIR` / `PROJECT_ROOT`, định tuyến 5 model ID chuẩn (`claude-sonnet-5-5-high`, `gemini-3.8-flash-high`, `gemini-4-argon-high`, `claude-opus-5-5-high`), kiểm tra CPU/RAM tương thích macOS (`sysctl -n hw.ncpu vm.loadavg`, `vm_stat`, `sysctl -n hw.memsize`), `TASK_COOLDOWN_SECONDS=30`, `ERROR_COOLDOWN_SECONDS=120`, `QUOTA_BACKOFF_SECONDS=900`, watchdog timeout, stale lock cleanup, trap `INT`/`TERM`, bảo vệ Git working tree, chặn lệnh phá hủy DB/Git, redact secret và cổng `verify_done_gate()` chống `DONE` giả.
- `qlpk-autonomous-framework/reports/agy-cli-capability-matrix.md`: Chuyển toàn bộ 12 hạng mục sang `VERIFIED` dựa trên bằng chứng từ `agy` `1.4.7`.
- `qlpk-autonomous-framework/AGENTS.md`, `PLANNER.md`, `REVIEWER.md`, `ROADMAP.md`, `PROGRESS.md`, `README.md`, `MORNING.md`, `reports/README.md`: Đồng bộ 100% model ID, vòng đời review cấp Task (tối đa 3 vòng), đường dẫn `PROJECT_ROOT` / `FRAMEWORK_DIR` và chỉ số theo dõi tài nguyên.

### 2. Kết quả thực thi Automated Test Matrix (`AT-00` → `AT-11`)
Bằng chứng chi tiết được lưu tại `qlpk-autonomous-framework/reports/workflow-dry-run.md` (`passed=12`, `failed=0`):

| Test ID | Kịch bản kiểm thử | Kết quả mong đợi | Kết quả thực tế | Trạng thái |
|---|---|---|---|---|
| `AT-00` | Kiểm tra cú pháp `bash -n run-agy.sh` | Exit code `0` | Exit code `0` | **PASS** *(EXECUTED)* |
| `AT-01` | Test FAIL chặn gọi Reviewer | `rc=13, reviewer_calls=0` | `rc=13, reviewer_calls=0` | **PASS** *(EXECUTED)* |
| `AT-02` | Test PASS gọi đúng Reviewer `gemini-4-argon-high` | `rc=0, coder=1, reviewer=1` | `rc=0, coder=1, reviewer=1` | **PASS** *(EXECUTED)* |
| `AT-03` | Reviewer FAIL kích hoạt Fix Loop → Retest → Re-review PASS | `rc=0, coder=2, reviewer=2` | `rc=0, coder=2, reviewer=2` | **PASS** *(EXECUTED)* |
| `AT-04` | Review FAIL 3 vòng liên tiếp chuyển `BLOCKED` | `rc=18, reviewer_calls=3` | `rc=18, reviewer_calls=3` | **PASS** *(EXECUTED)* |
| `AT-05` | CPU/RAM vượt ngưỡng chờ có giới hạn rồi chuyển `BLOCKED` | `rc=11, model_calls=0` | `rc=11, model_calls=0` | **PASS** *(EXECUTED)* |
| `AT-06` | Nghỉ `TASK_COOLDOWN_SECONDS` giữa hai task | `elapsed >= 2s` | `elapsed=2s` | **PASS** *(EXECUTED)* |
| `AT-07` | Timeout cắt tiến trình treo và giải phóng `run.lock` | `rc!=0, lock_removed=1` | `rc=12, lock_removed=1` | **PASS** *(EXECUTED)* |
| `AT-08` | Hết quota (`429`) chờ `QUOTA_BACKOFF_SECONDS` & giữ nguyên file chưa commit | `rc=75, file preserved` | `rc=75, content='important-uncommitted-work'` | **PASS** *(EXECUTED)* |
| `AT-09` | Working tree bẩn khiến framework dừng an toàn & bảo toàn file | `rc=10, file_kept=1, calls=0` | `rc=10, file_kept=1, calls=0` | **PASS** *(EXECUTED)* |
| `AT-10` | Model fallback ghi log tường minh (`FALLBACK_TRIGGERED`) | `rc=0, fallback_logged=1` | `rc=0, fallback_logged=1` | **PASS** *(EXECUTED)* |
| `AT-11` | Chống trạng thái `DONE` giả / báo cáo từ `RUN_ID` cũ | `rc!=0 (Rejected)` | `rc=1 (Rejected)` | **PASS** *(EXECUTED)* |

### 3. Cập nhật Mức độ Sẵn sàng (Updated Readiness Decision)
- **Tổng số file đạt chuẩn sau khi vá:** **19/19 file PASS** (+ 2 báo cáo kiểm chứng mới `reports/framework-audit.md` và `reports/workflow-dry-run.md`).
- **Mức độ sẵn sàng mới:** **`READY FOR CONTROLLED TEST` / `READY FOR LIMITED AUTOMATION`**.
