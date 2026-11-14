# Task Review Report: {{TASK_ID}}

- **RUN_ID**: {{RUN_ID}}
- **Task ID**: {{TASK_ID}}
- **Reviewer Model**: gemini-4-argon-medium
- **Timestamp**: {{TIMESTAMP}}
- **Associated Test Report**: `reports/tasks/{{TASK_ID}}-test.md` (RUN_ID: {{RUN_ID}})
- **OVERALL_REVIEW_RESULT**: {{OVERALL_REVIEW_RESULT}}

---

## 1. Kết Quả Đối Chiếu 14 Tiêu Chí Kiểm Duyệt (14-Point Checklist)

- [ ] **01. Scope Adherence:** Mọi file sửa/tạo đều nằm trong danh sách `allowed_files` của Task.
- [ ] **02. Syntax & Compilation:** Không có lỗi cú pháp, lint sạch, build thành công.
- [ ] **03. Test Execution Proof:** Báo cáo `{{TASK_ID}}-test.md` có `OVERALL_TEST_RESULT: PASS` và `exit_code == 0`.
- [ ] **04. Secrets Check:** Không có API keys, mật khẩu, access token hardcode trong mã nguồn.
- [ ] **05. Migration Safety:** Không chạy lệnh phá hủy bảng dữ liệu, có up/down đối xứng.
- [ ] **06. Multi-Tenancy Isolation:** Mọi query, job, controller đều nhận diện tường minh `tenant_id`.
- [ ] **07. N+1 Query Prevention:** Có eager loading `with(...)` cho các quan hệ Eloquent.
- [ ] **08. Transaction Integrity:** Các thao tác ghi nhiều bảng được bọc trong `DB::transaction(...)`.
- [ ] **09. Input Validation:** Backend có Form Request, Frontend có schema Zod chặt chẽ.
- [ ] **10. Error Handling:** Bọc try-catch, ném typed exception và ghi log rõ ràng.
- [ ] **11. Atomic UI & Tailwind:** Dùng component shadcn/ui và utility class Tailwind CSS v4.
- [ ] **12. Responsive Tables:** Bọc bảng trong `overflow-x-auto` và có `whitespace-nowrap` hợp lý.
- [ ] **13. Double-Submit Protection:** Các nút thao tác quan trọng có `isSubmitting`/`disabled`.
- [ ] **14. Clean Code:** Không còn `dd()`, `dump()`, `console.log()` hoặc dead code.

---

## 2. Nhận Xét Chi Tiết Của Reviewer

{{REVIEW_NOTES}}

---

## 3. Phán Quyết Kiểm Duyệt (Review Verdict JSON)

```json
{
  "task_id": "{{TASK_ID}}",
  "run_id": "{{RUN_ID}}",
  "verdict": "{{VERDICT_PASS_OR_FAIL}}",
  "checklist_score": "{{CHECKLIST_SCORE_OUT_OF_14}}",
  "notes": "{{BRIEF_VERDICT_REASON}}"
}
```
