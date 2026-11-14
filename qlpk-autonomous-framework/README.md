# QLPK Multi-Agent Safe Autonomous Framework (Laravel 13 + Next.js 16)

> **Bộ khung điều phối tự động hóa đa Agent (Multi-Agent Safe Orchestrator)**  
> Được thiết kế đặc thù cho hệ thống SaaS Viết Bài SEO Tự Động (`backend/` Laravel 12/13 + `frontend/` Next.js 16).  
> **Nguyên tắc cốt lõi:** Chạy bền bỉ qua đêm không tắt máy, tự phục hồi lỗi, bảo vệ tài nguyên phần cứng, và áp dụng cổng nghiệm thu **Anti-Fake-Done** (chỉ commit khi Test thật PASS và Reviewer PASS).

---

## 1. Cấu Trúc Thư Mục Điều Phối

```text
qlpk-autonomous-framework/
├── run-agy.sh                   # Script Bash điều phối chính (caffeinate, resource check, test runner, git commit)
├── AGENTS.md                    # Định nghĩa vai trò 4 Agents & Routing Model (Planner, Coder, Tester, Reviewer)
├── PLANNER.md                   # Hướng dẫn chia Task nguyên tử (5-15p), Dependency & State Machine
├── REVIEWER.md                  # Checklist 14 điểm kiểm duyệt code bắt buộc
├── ROADMAP.md                   # Đặc tả 10 Tasks chi tiết (Phase 1 P0, Phase 2 P1, Phase 3 P2)
├── PROGRESS.md                  # Bảng ma trận theo dõi tiến độ thời gian thực
├── templates/                   # Mẫu báo cáo Task, Review, Audit
│   ├── task-test-template.md    # Mẫu báo cáo Automated Test kèm RUN_ID
│   ├── task-review-template.md  # Mẫu thẩm định 14 tiêu chí & Verdict JSON
│   └── audit-report-template.md # Mẫu báo cáo Audit hệ thống
├── skills/                      # Cẩm nang kỹ thuật cho Coder & Reviewer
│   ├── laravel-backend/SKILL.md # Quy chuẩn Form Request, DB::transaction, N+1, Pint
│   ├── nextjs-frontend/SKILL.md # Quy chuẩn App Router, shadcn/ui, overflow-x-auto, isSubmitting
│   └── api-contract/SKILL.md    # Chuẩn hóa JSON envelope & HTTP status codes
├── logs/                        # Nơi lưu orchestrator.log và overnight.out
└── .agy-state/                  # Nơi lưu orchestrator.lock, STOP file, PID session
```

---

## 2. Hướng Dẫn Sử Dụng & Lệnh Điều Hành

### Bước 1: Cấp quyền thực thi cho script
```bash
chmod +x ./qlpk-autonomous-framework/run-agy.sh
```

### Bước 2: Chạy kiểm tra môi trường thử nghiệm (Dry-run, không tốn token)
```bash
./qlpk-autonomous-framework/run-agy.sh dry-run
```

### Bước 3: Kiểm tra trạng thái hệ thống & tài nguyên CPU/RAM
```bash
./qlpk-autonomous-framework/run-agy.sh status
```

### Bước 4: Khởi chạy tiến trình ngầm xuyên suốt qua đêm (Overnight Safe Run)
```bash
nohup caffeinate -dimsu env FORCE_RUN=1 CPU_LOAD_PER_CORE_LIMIT=3.0 MAX_TASKS_PER_SESSION=50 SESSION_TIMEOUT_SECONDS=43200 ./qlpk-autonomous-framework/run-agy.sh run > ./qlpk-autonomous-framework/logs/overnight.out 2>&1 &
```

### Bước 5: Theo dõi nhật ký thời gian thực
```bash
tail -f ./qlpk-autonomous-framework/logs/overnight.out
# hoặc xem log chi tiết
tail -f ./qlpk-autonomous-framework/logs/orchestrator.log
```

### Bước 6: Dừng an toàn khi cần can thiệp (Graceful Stop)
```bash
./qlpk-autonomous-framework/run-agy.sh stop
```
*(Lệnh này tạo file `.agy-state/STOP`, orchestrator sẽ hoàn thành task hiện tại hoặc dừng ngay ở boundary kế tiếp mà không làm bẩn mã nguồn).*

---

## 3. Các Cơ Chế Bảo Vệ Cốt Lõi

1. **Chống Máy Ngủ (macOS Sleep Assertion):** Sử dụng `caffeinate -dimsu -w $PPID` để máy Mac luôn chạy tác vụ AI xuyên suốt khi gập màn hình hoặc chạy ban đêm.
2. **Kiểm Soát Nhiệt Độ & Tràn RAM:** Kiểm tra `sysctl -n vm.loadavg` (ngưỡng `<= 3.0/core`) và `vm_stat` (RAM trống `>= 15%`) trước mỗi task. Nếu máy bị quá tải, tự động dừng nghỉ `15s` chờ hạ nhiệt tối đa `300s`.
3. **Cổng Nghiệm Thu Anti-Fake-Done:** Bắt buộc đồng thời:
   - `reports/tasks/<task_id>-test.md` có `OVERALL_TEST_RESULT: PASS` (exit code 0 từ lệnh thật).
   - `reports/tasks/<task_id>-review.md` có `OVERALL_REVIEW_RESULT: PASS` và verdict JSON `"verdict": "PASS"`.
   - Trùng khớp `RUN_ID` phiên hiện tại.
4. **Bảo Vệ Nhánh Git:** Chỉ cho phép commit lên nhánh `dev` (trừ khi có `FORCE_RUN=1`), chặn các lệnh nguy hiểm (`migrate:fresh`, `db:wipe`, `git reset --hard`).
5. **Cooldown Thông Minh:** Nghỉ `15s` giữa các task, nghỉ `30s` khi gặp lỗi kiểm thử, và lùi bước `15 phút` nếu gặp lỗi Quota/Rate Limit (429).
