# SKILL.md — Chuẩn Hóa API Contract Giữa Laravel & Next.js

> **Mục tiêu:** Đồng bộ cấu trúc dữ liệu JSON, mã trạng thái HTTP và thông báo lỗi giữa Backend (Laravel) và Frontend (Next.js).

---

## 1. Định Dạng Phản Hồi JSON Chuẩn (Standard Response Envelope)

### 1.1 Phản hồi thành công (Success Response)
Áp dụng cho các mã HTTP `200 OK`, `201 Created`, `202 Accepted`:

```json
{
  "success": true,
  "message": "Thao tác thành công",
  "data": {
    "id": 12,
    "title": "Hướng dẫn tối ưu SEO Onpage 2026",
    "status": "draft"
  },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 150
  }
}
```

### 1.2 Phản hồi lỗi kiểm tra hợp lệ (422 Unprocessable Content)
```json
{
  "success": false,
  "message": "Dữ liệu gửi lên không hợp lệ.",
  "errors": {
    "keyword": ["Trường từ khóa không được để trống."],
    "wordpress_site_id": ["Website WordPress được chọn không tồn tại."]
  }
}
```

### 1.3 Phản hồi lỗi hệ thống / Từ chối cấp quyền (401, 403, 404, 500)
```json
{
  "success": false,
  "message": "Bạn không có quyền thao tác trên tài nguyên này.",
  "error_code": "FORBIDDEN_TENANT_ACCESS"
}
```

---

## 2. Bảng Mã Lỗi HTTP Quy Ước

| Mã HTTP | Tên chuẩn | Mục đích sử dụng |
| :---: | :--- | :--- |
| **200** | `OK` | Truy vấn hoặc cập nhật tài nguyên thành công. |
| **201** | `Created` | Tạo mới tài nguyên thành công (Keyword, Campaign, Article). |
| **202** | `Accepted` | Đã tiếp nhận yêu cầu và đẩy vào Background Job xử lý (Publish, Generate). |
| **204** | `No Content` | Xóa tài nguyên thành công, không có body trả về. |
| **401** | `Unauthorized` | Thiếu hoặc hết hạn token Sanctum $\rightarrow$ Frontend tự động redirect về `/login`. |
| **402** | `Payment Required` | Hết quota số bài viết hoặc vượt ngân sách chi phí AI trong tháng. |
| **403** | `Forbidden` | Không có quyền truy cập dữ liệu của tenant khác. |
| **404** | `Not Found` | Không tìm thấy tài nguyên theo ID yêu cầu. |
| **422** | `Unprocessable Content` | Form Request validation thất bại. |
| **429** | `Too Many Requests` | Vượt giới hạn Rate Limit (AI Generation, SERP Lookups, Login Brute-force). |
| **500** | `Internal Server Error` | Lỗi máy chủ không mong muốn, bắt buộc ghi log chi tiết. |
