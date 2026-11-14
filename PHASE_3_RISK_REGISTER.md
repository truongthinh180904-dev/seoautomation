# PHASE_3_RISK_REGISTER.md — Sổ Đăng Ký Rủi Ro & Chiến Lược Kiểm Soát Phase 3
## AuraSEO – SEO Automation & Intelligence

> **Dự án:** Multi-Tenant SaaS Auto-SEO Content Automation  
> **Mục tiêu:** Nhận diện toàn diện các rủi ro kỹ thuật, bảo mật, vận hành, tài chính và pháp lý trước khi viết dòng code đầu tiên của Phase 3.  

---

## 1. MA TRẬN ĐÁNH GIÁ MỨC ĐỘ RỦI RO (RISK HEATMAP)

```text
ẢNH HƯỞNG
   ▲
CAO│   [RISK-SEC-1]   [RISK-LEG-1]   [RISK-FIN-1]
   │   [RISK-SEC-2]   [RISK-OPS-1]
TRUNG│ [RISK-TECH-1]  [RISK-TECH-2]  [RISK-TECH-3]
BÌNH│  [RISK-OPS-2]
THẤP│                 [RISK-DATA-1]
   └────────────────────────────────────────► XÁC SUẤT
       THẤP           TRUNG BÌNH      CAO
```

---

## 2. BẢNG CHI TIẾT SỔ ĐĂNG KÝ RỦI RO (RISK REGISTER)

| Mã Rủi Ro | Phân Loại | Mô Tả Rủi Ro Chi Tiết | Mức Độ | Biện Pháp Kiểm Soát & Giải Pháp Kỹ Thuật (Mitigation Strategy) | Người Chịu Trách Nhiệm |
| :---: | :--- | :--- | :---: | :--- | :---: |
| **RISK-SEC-1** | Bảo Mật | **Rò rỉ Google OAuth Access & Refresh Token:**<br>Token bị lộ khi export database, hiển thị trên giao diện hoặc in ra logs của server. | **CRITICAL** | 1. Sử dụng Laravel Attribute Encryption (`'access_token' => 'encrypted'`, `'refresh_token' => 'encrypted'`).<br>2. Bổ sung các trường vào `SENSITIVE_KEYS` trong `AuditLogService` để tự động che giấu.<br>3. Không bao giờ serialize tokens sang JSON trả về cho Frontend. | Backend Architect / Security Auditor |
| **RISK-SEC-2** | Bảo Mật | **Lỗ hổng Cross-Tenant IDOR:**<br>Khách hàng ở Workspace A có thể xem trộm dữ liệu GSC, GA4, từ khóa hoặc sửa bài viết của Workspace B. | **HIGH** | 1. 100% Model mới kế thừa trait `BelongsToWorkspace`.<br>2. Mọi truy vấn bắt buộc có Global Scope `where('workspace_id', $currentWorkspaceId)`.<br>3. FormRequest kiểm tra quyền sở hữu trước khi thực thi. | Backend Coder & Reviewer Agent |
| **RISK-LEG-1** | Pháp Lý | **Tự ý cào Google Search (Scraping Violations):**<br>Sử dụng Puppeteer/Playwright cào Google SERP vi phạm Google ToS, bị CAPTCHA chặn IP và đối mặt rủi ro pháp lý. | **CRITICAL** | **TUYỆT ĐỐI CẤM TỰ CÀO GOOGLE SERP.** Bắt buộc sử dụng 100% qua nhà cung cấp hợp pháp được cấp phép: **Serper.dev API**. | Senior Architect |
| **RISK-FIN-1** | Tài Chính | **Vòng lặp gọi API Serper.dev gây tốn kém tiền:**<br>Job tracking chạy vòng lặp vô tận khiến chi phí Serper bị đội lên hàng nghìn USD. | **HIGH** | 1. Áp dụng giới hạn số từ khóa nghiêm ngặt theo gói cước (Plan Limits).<br>2. Rate Limiter tối đa 5 req/s.<br>3. Khóa chống chạy lại trong ngày (`UNIQUE KEY uk_rank_check (rank_tracked_keyword_id, check_date)`). | Billing & Queue Specialist |
| **RISK-OPS-1** | Vận Hành | **Nghẽn Hàng Đợi (Queue Storm) & Sập Redis:**<br>Hàng nghìn website cùng kích hoạt đồng bộ GSC và GA4 vào lúc 00:00 gây nghẽn Redis. | **HIGH** | 1. Sử dụng Jitter Scheduling (chia khung giờ ngẫu nhiên từ 01:00 đến 05:00 sáng).<br>2. Tách biệt các hàng đợi độc lập: `queue:gsc-sync`, `queue:ga4-sync`, `queue:rank-tracking`. | DevOps / Queue Engineer |
| **RISK-OPS-2** | Vận Hành | **Chạy chồng chéo tác vụ (Scheduler Overlap):**<br>Job đồng bộ ngày trước chạy chưa xong thì job ngày hôm sau đã được Scheduler kích hoạt. | **MEDIUM** | Bắt buộc sử dụng `$schedule->command(...)->withoutOverlapping(60)->runInBackground()`. | Backend Architect |
| **RISK-TECH-1**| Kỹ Thuật | **Độ trễ dữ liệu 72h của Google Search Console:**<br>Số liệu 3 ngày gần nhất của GSC chưa được chốt (`dataState = FRESH`), nếu lấy sẽ bị sai lệch clicks. | **MEDIUM** | 1. Chỉ lấy dữ liệu khi Google đánh dấu `dataState = FINAL`.<br>2. Cơ chế quét bù (Backfill buffer) 3 ngày liền kề để cập nhật số liệu chuẩn hóa. | Data Engineer |
| **RISK-TECH-2**| Kỹ Thuật | **Lệch Múi Giờ Giữa GA4 và Website (Timezone Drift):**<br>GA4 tính ngày theo timezone cấu hình trong property, trong khi server tính theo UTC, dẫn đến lệch số liệu ngày. | **MEDIUM** | Đọc trường `timeZone` từ GA4 Property Metadata và chuẩn hóa khoảng thời gian truy vấn theo múi giờ địa phương của website. | Data Engineer |
| **RISK-TECH-3**| Kỹ Thuật | **Token Hết Hạn Bất Ngờ Khi Đang Đồng Bộ Ngầm:**<br>`access_token` của Google hết hạn sau 60 phút, dẫn đến job đồng bộ nửa chừng bị lỗi HTTP 401. | **MEDIUM** | Xây dựng hàm kiểm tra `refreshTokenIfNeeded()` trước mỗi lượt gọi API; nếu còn dưới 5 phút thì tự động đổi token mới trước. | Integration Engineer |
| **RISK-DATA-1**| Dữ Liệu | **Phình to Cơ Sở Dữ Liệu Khi Lưu Dữ Liệu Lịch Sử:**<br>Bảng dữ liệu hàng ngày lưu hàng triệu dòng URLs và Queries gây chậm truy vấn. | **LOW** | 1. Áp dụng chuẩn hóa MD5 Hash (`page_hash`, `query_hash`).<br>2. Composite Indexes chuẩn mực theo `(website_id, date)`.<br>3. Thiết kế sẵn cấu trúc Partitioning theo tháng. | Database Administrator |

---

## 3. MA TRẬN PHÂN ĐỊNH TRÁCH NHIỆM (RACI MATRIX)

| Hoạt Động / Nhiệm Vụ | Product Owner (User) | Senior Architect | Coder Agent (Gemini Flash) | Reviewer Agent (Gemini Argon) |
| :--- | :---: | :---: | :---: | :---: |
| **Cung cấp Google OAuth Credentials** | **A / R** | C | I | I |
| **Thiết kế Kiến trúc & Task Breakdown** | I | **A / R** | C | C |
| **Lập trình Code theo Allowed Files** | I | C | **R** | A |
| **Chạy Test Suite & Khắc phục Lỗi** | I | C | **R** | A |
| **Thẩm định Diff & Cấp chứng nhận PASS**| I | C | I | **A / R** |
| **Phê duyệt Triển khai Production** | **A** | R | I | C |

*Ký hiệu:*
- **R (Responsible):** Người trực tiếp thực hiện công việc.
- **A (Accountable):** Người chịu trách nhiệm phê duyệt cuối cùng.
- **C (Consulted):** Người được tham vấn chuyên môn.
- **I (Informed):** Người được thông báo kết quả.

---

## 4. QUY TRÌNH XỬ LÝ SỰ CỐ & ROLLBACK (INCIDENT RESPONSE)

1. **Khi phát hiện lỗi rò rỉ bảo mật hoặc vi phạm Tenant:**
   - Ngay lập tức thu hồi token (`GoogleOAuthService::revokeToken`).
   - Revert commit gây lỗi về commit ổn định gần nhất trên nhánh `dev`.
2. **Khi phát hiện suy giảm hiệu năng truy vấn Dashboard:**
   - Kiểm tra `EXPLAIN` câu lệnh SQL; bổ sung composite index thích hợp.
   - Bật cache Redis với thời gian sống 1 giờ cho các truy vấn tổng hợp lịch sử.
3. **Khi API đối tác gặp sự cố (Google / Serper downtime):**
   - Không đánh dấu job là thất bại vĩnh viễn; chuyển sang trạng thái chờ retry với Exponential Backoff.
   - Báo cáo rõ ràng lỗi kết nối ngoại vi cho người dùng trên giao diện.

---
**Tài liệu được lập bởi:** Senior Software Architect & Security Reviewer  
**Trạng thái:** Sẵn sàng quản lý rủi ro xuyên suốt quá trình thực thi Phase 3.
