# REVIEWER.md — Checklist 14 Tiêu Chí Kiểm Duyệt Code (Anti-Fake-Done)

> **Mục tiêu:** Cung cấp bộ quy tắc thẩm định độc lập 14 điểm cho REVIEWER model.  
> **Nguyên tắc cốt lõi:** Không bao giờ tin lời tự nhận của Coder. Chỉ cấp `{"verdict": "PASS"}` khi git diff và báo cáo kiểm thử thực tế thỏa mãn toàn bộ 14 tiêu chí.

---

## 1. Bảng 14 Tiêu Chí Kiểm Duyệt Bắt Buộc

| STT | Tiêu chí | Mô tả chi tiết kiểm tra | Trọng yếu |
| :---: | :--- | :--- | :---: |
| **01** | **Scope Adherence (Phạm vi file)** | Kiểm tra `git diff --stat`. Coder **chỉ được phép** tạo hoặc sửa các file nằm chính xác trong danh sách `allowed_files` của Task. Nếu có file thừa $\rightarrow$ **REJECT**. | **P0** |
| **02** | **No Compilation/Syntax Errors** | Mã nguồn không có lỗi cú pháp, lint sạch. Code PHP qua được `pint`, TypeScript không có lỗi type check. | **P0** |
| **03** | **Automated Test Proof** | Bắt buộc phải có file `reports/tasks/<task_id>-test.md` với `OVERALL_TEST_RESULT: PASS` và `exit_code == 0`. Cùng `RUN_ID` với phiên hiện tại. | **P0** |
| **04** | **No Hardcoded Secrets** | Không hardcode API key, DB password, App Password vào source code. Tất cả phải đọc qua `config(...)` và `.env`. | **P0** |
| **05** | **Database Migration Safety** | Tuyệt đối không xóa bảng (DROP TABLE), không sửa schema bất thường. Migration mới phải có `up()` và `down()` đối xứng, đánh index trường tìm kiếm. | **P0** |
| **06** | **Multi-Tenancy Isolation** | Đảm bảo tính cô lập dữ liệu. Mọi truy vấn Eloquent, Background Job, Queue Worker đều phải gán chặt với `tenant_id`. Không để hở scope trong Queue. | **P0** |
| **07** | **N+1 Query Prevention** | Kiểm tra các truy vấn có quan hệ Eloquent: Bắt buộc dùng eager loading `with(...)`. Không lặp qua collection để gọi query con. | **P1** |
| **08** | **Database Transaction Integrity** | Các thao tác ghi nhiều bảng liên hoàn (VD: trừ quota, tạo bài, tạo media asset) phải bọc trong `DB::transaction(...)`. | **P1** |
| **09** | **Validation Strictness** | Backend dùng Laravel Form Request riêng biệt, Frontend dùng React Hook Form kết hợp Zod validation. Không validate sơ sài trong Controller. | **P1** |
| **10** | **Error Handling & Typed Exceptions** | Bọc khối try-catch hợp lý, ném typed exception (`QuotaExceededException`, `ProviderException`), ghi log chi tiết vào hệ thống. | **P1** |
| **11** | **Atomic UI & Tailwind Standards** | Frontend Next.js dùng component nguyên tử của `shadcn/ui`, Tailwind CSS v4 chuẩn mực. Không lặp code UI CSS thô. | **P1** |
| **12** | **Responsive Table & Layout** | Mọi thẻ `<table>` phải được bọc trong container `overflow-x-auto`. Các cột ngắn (status, date, action) phải có `whitespace-nowrap`. | **P2** |
| **13** | **Double-Submit Protection** | Mọi Form hoặc Action buttons (Publish, Start Campaign, Import) phải có trạng thái `isSubmitting`/`disabled` kèm spinner chống bấm đúp. | **P2** |
| **14** | **Clean Code & No Debug Leftovers** | Không để lại `dd()`, `dump()`, `var_dump()`, `console.log()` hoặc code chú thích chết trong source code. | **P2** |

---

## 2. Định Dạng Báo Cáo Phê Duyệt Của Reviewer

Reviewer ghi kết quả vào file `reports/tasks/<task_id>-review.md` theo mẫu sau:

```markdown
# Task Review Report: <task_id>

- **RUN_ID**: <run_id>
- **Task ID**: <task_id>
- **Reviewer Model**: gemini-4-argon-medium
- **Timestamp**: YYYY-MM-DD HH:MM:SS
- **OVERALL_REVIEW_RESULT**: PASS (hoặc FAIL)

## 14-Point Checklist Verification
1. [x] Scope Adherence: All modified files match allowed_files
2. [x] Compilation & Syntax: PASS
3. [x] Test Execution Verified: exit_code == 0
4. [x] Secrets Check: No hardcoded credentials
5. [x] Migration Safety: Non-destructive
6. [x] Multi-tenancy Isolation: Verified
7. [x] N+1 Query: Eager loading applied
8. [x] DB Transactions: Applied
9. [x] Input Validation: FormRequest / Zod verified
10. [x] Error Handling: Structured try-catch
11. [x] UI Standards: shadcn/ui + Tailwind v4
12. [x] Responsive Tables: overflow-x-auto present
13. [x] Form Protection: isSubmitting disabled state present
14. [x] Cleanliness: No debug leftovers

## Review Verdict JSON
```json
{
  "task_id": "<task_id>",
  "run_id": "<run_id>",
  "verdict": "PASS",
  "checklist_score": "14/14",
  "notes": "Task verified completely against all 14 points."
}
```
```
