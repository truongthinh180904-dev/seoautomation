# Reports Directory

Lưu trữ toàn bộ bằng chứng thực thi theo từng lần chạy (`RUN_ID`). Tuyệt đối không ghi secret (`.env`, password, token) hoặc dữ liệu bệnh nhân vào báo cáo.

## Cấu trúc chuẩn
- `tasks/<task_id>.md`: Báo cáo thực thi của Coder cho từng task.
- `tasks/<task_id>-test.md`: Báo cáo kết quả chạy `test_commands` của Automated Tester (kèm `RUN_ID`, lệnh, exit code, thời gian, log đã redact).
- `tasks/<task_id>-review-round-<n>.md`: Báo cáo đánh giá của Reviewer cho vòng `<n>` (`1..3`) kèm `RUN_ID`, model, verdict (`PASS|FAIL|BLOCKED`) và danh sách findings.
- `tasks/<task_id>-final.md`: Báo cáo nghiệm thu cuối cùng của task khi đạt `DONE` hoặc `BLOCKED`.
- `features/<feature_id>-test.md`: Báo cáo kiểm thử tổng hợp cấp Feature.
- `features/<feature_id>-review-round-<n>.md`: Báo cáo review cấp Feature.
- `features/<feature_id>-final.md`: Báo cáo nghiệm thu cuối cùng cấp Feature.
- `agy-cli-capability-matrix.md`: Bảng xác minh khả năng thực tế của `agy` CLI.
- `framework-audit.md`: Báo cáo kiểm toán toàn diện 19 file của framework.
- `workflow-dry-run.md`: Báo cáo kết quả chạy bộ kiểm thử tự động `AT-00..AT-11` của workflow.
