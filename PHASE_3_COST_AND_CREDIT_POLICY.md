# PHASE_3_COST_AND_CREDIT_POLICY.md — Chính Sách Chi Phí, Quota & Sổ Cái Credit Phase 3
## AuraSEO – SEO Automation & Intelligence

> **Dự án:** Multi-Tenant SaaS Auto-SEO Content Automation  
> **Nguyên tắc bảo vệ số dư:** **ZERO-LOSS & ANTI-DOUBLE-DEDUCTION GUARANTEE**  
> **Cơ chế:** Sử dụng duy nhất `CreditLedgerService` sẵn có của Phase 1. Cấm tuyệt đối tạo ledger thứ 2 hoặc trừ tiền bừa bãi.  

---

## 1. NGUYÊN TẮC TÀI CHÍNH BẤT BIẾN (CARDINAL FINANCIAL RULES)

1. **Đồng bộ Dữ liệu Google (GSC & GA4) = 0 Credit AI:**  
   Người dùng kết nối Google và đồng bộ hàng triệu dòng dữ liệu từ Google Search Console và Google Analytics 4 hoàn toàn không bị tính bất kỳ một credit AI nào. Đây là tính năng hạ tầng cơ bản của nền tảng SaaS.
2. **Quét Phát Hiện Bất Thường (SEO Intelligence Rule-Based) = 0 Credit AI:**  
   Các thuật toán Heuristics phân tích dữ liệu GSC/GA4/Rank Tracking để phát hiện tụt hạng, CTR thấp hay ăn thịt từ khóa chạy thuần túy trên CPU máy chủ bằng PHP, không gọi LLM nên tiêu tốn **0 Credit**.
3. **Chỉ Trừ Credit Khi Gọi AI Chuyên Sâu Hoặc Vượt Quota Rank Tracking:**  
   Chỉ trừ credit khi người dùng kích hoạt **AI Content Refresh** (sinh đề xuất tối ưu nội dung mới) hoặc kích hoạt theo dõi số lượng từ khóa vượt định mức gói cước.
4. **Quy trình 3 Bước Reserve $\rightarrow$ Commit $\rightarrow$ Release:**  
   Khi bắt đầu tác vụ tốn phí, hệ thống tạm giữ credit (`reserveCredits`). Khi hoàn thành xuất sắc, chuyển sang trừ chính thức (`deductCredits`). Nếu xảy ra lỗi hoặc người dùng bấm Hủy bỏ, hệ thống lập tức hoàn lại 100% credit đã tạm giữ (`releaseCredits`).
5. **Khóa Độc Bản Chống Trừ Tiền Trùng Lặp (Idempotency Key):**  
   Mọi giao dịch khấu trừ credit đều đi kèm khóa duy nhất theo định dạng:  
   `idempotency_key = p3_{action}_{workspace_id}_{target_id}_{date_timestamp}`.

---

## 2. MA TRẬN CHI PHÍ & ĐỊNH GIÁ CREDIT PHA 3

| Phân Hệ / Hành Động Nghiệp Vụ | Nhà Cung Cấp / Công Nghệ | Chi Phí Hạ Tầng Thực Tế (USD) | Khấu Trừ Khách Hàng (Credits) | Ghi Chú & Cơ Chế Xử Lý |
| :--- | :--- | :---: | :---: | :--- |
| **Đồng bộ GSC hàng ngày** | Google Search Console API | $0.00 | **0 Credit** | Nền tảng miễn phí; chạy qua Queue ngầm. |
| **Đồng bộ GA4 hàng ngày** | Google Analytics Data API | $0.00 | **0 Credit** | Nền tảng miễn phí; chạy qua Queue ngầm. |
| **Quét Heuristics Anomaly** | PHP Engine (Thuần Server) | $0.00 | **0 Credit** | Chạy sau khi đồng bộ xong, 0 credit. |
| **Theo dõi Rank trong hạn mức** | Serper.dev API | $0.001 / từ khóa | **Bao gồm trong Plan** | Basic: 20 từ khóa; Pro: 100 từ khóa; Enterprise: 500 từ khóa. |
| **Theo dõi Rank vượt hạn mức** | Serper.dev API | $0.001 / từ khóa | **1 Credit / 5 từ khóa** | Chỉ trừ khi người dùng chủ động thêm từ khóa vượt plan. |
| **Lập Kế hoạch AI Content Refresh** | Gemini 2.5 Flash | ~$0.0008 | **2 Credits / bài** | Phân tích bài cũ + nạp bối cảnh GSC + sinh đề xuất. |
| **AI Copilot viết lại đoạn văn** | Gemini 2.5 Flash | ~$0.0003 | **1 Credit / lượt** | Tái sử dụng qua `ContentStudioService::assist`. |
| **Chấm điểm SEO trước/sau** | `SeoScoringEngine` | $0.00 | **0 Credit** | Thuật toán toán học thuần PHP (14 tiêu chí, < 5ms). |
| **Cập nhật bài viết lên WordPress**| WordPress REST API | $0.00 | **0 Credit** | Miễn phí; chỉ cần người dùng duyệt hành động. |

---

## 3. CẤU HÌNH BỔ SUNG TRONG `config/ai_costs.php`

Mở rộng file cấu hình `backend/config/ai_costs.php` với các thông số định mức Phase 3:

```php
// Bổ sung vào mảng 'credits' trong config/ai_costs.php
'credits' => [
    'standard_article' => 20,
    'serp_lookup' => 1,
    'image_generation' => 5,
    'seo_autofix' => 2,
    
    // === ĐỊNH MỨC MỚI PHASE 3 ===
    'content_refresh_plan' => 2,        // Lập kế hoạch làm mới bài viết cũ
    'rank_tracking_extra_batch' => 1,   // Theo dõi thêm 5 từ khóa ngoài hạn mức
    'gsc_sync' => 0,                    // 0 Credit
    'ga4_sync' => 0,                    // 0 Credit
    'seo_intelligence_scan' => 0,       // 0 Credit
],

// Hạn mức từ khóa theo dõi thứ hạng theo gói cước
'rank_tracking_limits' => [
    'starter' => 20,
    'pro' => 100,
    'business' => 300,
    'agency' => 1000,
],
```

---

## 4. BẢO VỆ SỐ DƯ & QUY TRÌNH HOÀN TIỀN KHI LỖI (ZERO-LOSS WORKFLOW)

```mermaid
sequenceDiagram
    autonumber
    actor User as Người dùng / Chatbot
    participant Opt as ContentRefreshService
    participant Ledger as CreditLedgerService
    participant AI as Gemini AI Provider
    participant DB as MySQL Database

    User->>Opt: Yêu cầu tối ưu bài viết cũ ID #25
    Opt->>Ledger: reserveCredits(workspace, amount: 2, ref: "content_refresh", id: 25)
    
    alt Số dư khả dụng KHÔNG đủ (< 2 credit)
        Ledger-->>Opt: Ném ngoại lệ InsufficientCreditsException (HTTP 402)
        Opt-->>User: Báo lỗi "Không đủ credit. Vui lòng nạp thêm."
    else Số dư khả dụng ĐỦ
        Ledger->>DB: Tạm giữ 2 credit (available -= 2, reserved += 2)
        Ledger-->>Opt: Xác nhận giữ tiền thành công
        
        Opt->>AI: Gửi prompt phân tích bối cảnh bài viết
        
        alt AI xử lý THÀNH CÔNG
            AI-->>Opt: Trả về đề xuất tối ưu nội dung mới
            Opt->>Ledger: deductCredits(workspace, amount: 2, fromReserved: true)
            Ledger->>DB: Khấu trừ chính thức (reserved -= 2, cycle_used += 2)
            Opt-->>User: Trả về bản Diff trước/sau cho người dùng xem
        else AI bị TIMEOUT / LỖI MẠNG
            AI-->>Opt: Ném ngoại lệ ProviderTimeoutException
            Opt->>Ledger: releaseCredits(workspace, amount: 2, ref: "content_refresh", id: 25)
            Ledger->>DB: Hoàn trả 100% (reserved -= 2, available += 2)
            Opt-->>User: Báo lỗi kết nối AI, đảm bảo KHÔNG mất credit
        end
    end
```

---

## 5. THEO DÕI BIÊN LỢI NHUẬN & NHẬT KÝ CHI PHÍ THỰC TẾ

Toàn bộ chi phí thực tế phát sinh từ API ngoại vi được ghi nhận minh bạch vào 2 bảng dữ liệu:
1. **`ai_usage_logs`:** Ghi nhận chi tiết token prompt, token completion, model (Gemini 2.5 Flash), thời gian xử lý và chi phí USD quy đổi theo biểu giá nhà cung cấp.
2. **`credit_transactions`:** Ghi nhận lịch sử tăng giảm số dư của Workspace với mô tả chi tiết, actor thực hiện và mã `idempotency_key` chống trừ trùng.

---
**Tài liệu được lập bởi:** Senior Software Architect & Financial Systems Specialist  
**Trạng thái:** Sẵn sàng áp dụng cho toàn bộ Phase 3.
