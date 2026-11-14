# Workflow Isolated Self-Test Report (AT-00 .. AT-11)

- **Executed at:** `2026-10-26T17:14:26+0700`
- **Total Tests:** `12`
- **Passed:** `12`
- **Failed:** `0`
- **Overall Result:** `PASS`

| Test ID | Scenario | Expected | Actual | Result |
|---|---|---|---|---|
| `AT-00` | Kiểm tra cú pháp `bash -n run-agy.sh` | Exit code 0 | Exit code 0 | **PASS** |
| `AT-01` | Test FAIL chặn gọi Reviewer | rc=13, reviewer_calls=0 | rc=13, reviewer_calls=0 | **PASS** |
| `AT-02` | Test PASS gọi đúng Reviewer `gemini-4-argon-high` | rc=0, coder=1, reviewer=1 | rc=0, coder=1, reviewer=1 | **PASS** |
| `AT-03` | Reviewer FAIL kích hoạt Fix Loop -> Retest -> Re-review PASS | rc=0, coder=2, reviewer=2 | rc=0, coder=2, reviewer=2 | **PASS** |
| `AT-04` | Review FAIL 3 vòng liên tiếp chuyển `BLOCKED` | rc=18, reviewer_calls=3 | rc=18, reviewer_calls=3 | **PASS** |
| `AT-05` | CPU/RAM vượt ngưỡng chờ có giới hạn rồi chuyển `BLOCKED` | rc=11, model_calls=0 | rc=11, model_calls=0 | **PASS** |
| `AT-06` | Nghỉ `TASK_COOLDOWN_SECONDS` giữa hai task | elapsed >= 2s | elapsed=2s | **PASS** |
| `AT-07` | Timeout cắt tiến trình treo và giải phóng `run.lock` | rc!=0, lock_removed=1 | rc=12, lock_removed=1 | **PASS** |
| `AT-08` | Hết quota (`429`) chờ `QUOTA_BACKOFF_SECONDS` & giữ nguyên file chưa commit | rc=75, file preserved | rc=75, content='important-uncommitted-work' | **PASS** |
| `AT-09` | Working tree bẩn khiến framework dừng an toàn & bảo toàn file | rc=10, file_kept=1, calls=0 | rc=10, file_kept=1, calls=0 | **PASS** |
| `AT-10` | Model fallback ghi log tường minh (`FALLBACK_TRIGGERED`) | rc=0, fallback_logged=1 | rc=0, fallback_logged=1 | **PASS** |
| `AT-11` | Chống trạng thái `DONE` giả / báo cáo từ `RUN_ID` cũ | rc!=0 (Rejected) | rc=1 (Rejected) | **PASS** |
