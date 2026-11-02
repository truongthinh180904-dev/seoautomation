# Task Test Report: {{TASK_ID}}

- **RUN_ID**: {{RUN_ID}}
- **Task ID**: {{TASK_ID}}
- **Tester**: Shell Runner (Real Bash Subshell)
- **Timestamp Start**: {{START_TIME}}
- **Timestamp End**: {{END_TIME}}
- **Command Executed**: `{{TEST_COMMAND}}`
- **Exit Code**: {{EXIT_CODE}}
- **OVERALL_TEST_RESULT**: {{OVERALL_TEST_RESULT}}

---

## 1. Kết Quả Thực Thi (Execution Summary)

* **Trạng thái:** {{STATUS_TEXT}}
* **Thời gian thực thi:** {{DURATION_SECONDS}}s
* **Mã thoát (Exit Code):** {{EXIT_CODE}} (Yêu cầu `0` để đạt chuẩn PASS)

---

## 2. Nhật Ký Chi Tiết (Last 50 Lines Output)

```text
{{OUTPUT_LOG}}
```

---

## 3. Kết Luận
* Căn cứ mã thoát lệnh shell, bài kiểm tra được đánh giá là: **{{OVERALL_TEST_RESULT}}**.
* Báo cáo này là điều kiện cần bắt buộc để chuyển sang bước thẩm định của REVIEWER Model.
