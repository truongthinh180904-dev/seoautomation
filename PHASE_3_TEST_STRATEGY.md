# PHASE_3_TEST_STRATEGY.md — Chiến Lược Kiểm Thử Tự Động Toàn Diện Phase 3
## AuraSEO – SEO Automation & Intelligence

> **Dự án:** Multi-Tenant SaaS Auto-SEO Content Automation  
> **Nguyên tắc vàng:** **ANTI-FAKE-DONE** — Không có test chạy thành công thật với `exit_code == 0` thì không được coi là hoàn thành task.  
> **Môi trường:** 100% Isolated Sandbox, Mock toàn bộ External APIs (Google, Serper, WordPress, AI Providers).  

---

## 1. NGUYÊN TẮC KIỂM THỬ BẮT BUỘC (TESTING PHILOSOPHY)

1. **Tuyệt đối không gọi External APIs thật trong quá trình chạy test:**  
   Toàn bộ các lệnh gọi đến Google OAuth, Google Search Console, Google Analytics 4, Serper.dev, WordPress REST API và các nhà cung cấp AI (Gemini, OpenAI) bắt buộc phải được giả lập bằng `Http::fake()` hoặc Mockery container bindings. Tránh phát sinh chi phí, tránh phụ thuộc internet và bảo đảm tốc độ thực thi test suite < 30 giây.
2. **Kiểm thử biên giới Tenant (Multi-Tenant Boundary Testing):**  
   Mọi bài test liên quan đến dữ liệu đều bắt buộc khởi tạo tối thiểu 2 Workspaces độc lập (Workspace A và Workspace B) để chứng minh rằng người dùng thuộc Workspace A không bao giờ có thể xem, sửa hoặc xóa dữ liệu của Workspace B.
3. **Kiểm thử tài chính Zero-Loss (Financial Ledger Invariant):**  
   Mọi thao tác trừ credit phải kiểm tra số dư trước và sau. Khi có ngoại lệ hoặc kịch bản hủy bỏ, credit đã giữ phải được hoàn trả 100% về số dư khả dụng (`available_credits`).
4. **Kiểm thử tính bất biến (Idempotency Testing):**  
   Chạy cùng một Job đồng bộ 2 hoặc 3 lần liên tiếp với cùng một tập dữ liệu giả lập; số lượng bản ghi trong cơ sở dữ liệu phải không đổi.

---

## 2. MA TRẬN PHÂN TẦNG KIỂM THỬ (LAYERED TESTING MATRIX)

```text
▲ [TẦNG 5: E2E CLOSED-LOOP WORKFLOW TEST]  -> EndToEndPhase3WorkflowTest (GSC -> Rank -> Insight -> Studio -> WP)
│
├─► [TẦNG 4: BẢO MẬT & IDOR TESTS]         -> Phase3SecurityAuditTest (Tenant Boundary, Token Encryption, SSRF)
│
├─► [TẦNG 3: FINANCIAL & IDEMPOTENCY]      -> CreditLedgerZeroLossTest (Reserve -> Commit -> Release, Idempotency)
│
├─► [TẦNG 2: FEATURE & INTEGRATION TESTS]  -> GSC, GA4, Rank Tracking, Data Warehouse, Studio Diff Tests
│
▼ [TẦNG 1: UNIT TESTS & DATA INTEGRITY]    -> DTOs, MD5 Hashing, Heuristics Evaluators, Scoring Engine
```

---

## 3. CHI TIẾT CÁC TỆP KIỂM THỬ THEO TỪNG TASK

| Task | Tệp Kiểm Thử Tương Ứng | Kịch Bản Kiểm Thử Trọng Tâm | Lệnh Thực Thi Kiểm Thử |
| :---: | :--- | :--- | :--- |
| **P3.1** | `tests/Feature/IntegrationFoundationTest.php` | • Tạo `GoogleAccount`, kiểm tra `access_token` được mã hóa AES trong DB.<br>• Kiểm tra `TokenVaultService` giải mã chính xác.<br>• Kiểm tra Workspace 1 không xem được tài khoản Workspace 2. | `php artisan test --filter=IntegrationFoundationTest` |
| **P3.2** | `tests/Feature/GoogleSearchConsoleTest.php` | • Mock OAuth callback code $\rightarrow$ token.<br>• Mock `sites.list` lấy danh sách properties.<br>• Mock Search Analytics API với 50.000 dòng phân trang 2 batch.<br>• Kiểm tra auto-refresh token khi `token_expires_at` quá hạn. | `php artisan test --filter=GoogleSearchConsoleTest` |
| **P3.3** | `tests/Feature/GoogleAnalytics4Test.php` | • Mock `properties.list` và lưu đúng timezone.<br>• Mock `runReport` trả về sessions, users, engagement.<br>• Kiểm tra thuật toán cắt bỏ UTM parameter và map đúng `article_id`. | `php artisan test --filter=GoogleAnalytics4Test` |
| **P3.4** | `tests/Feature/SeoDataWarehouseTest.php` | • Kiểm tra tính toán MD5 hash cho URL và query dài.<br>• Chạy 2 lần job đồng bộ cùng ngày: Xác nhận không tạo bản ghi trùng lặp (Upsert idempotent).<br>• Query tổng hợp theo ngày và tính tỷ lệ tăng trưởng $\Delta\%$. | `php artisan test --filter=SeoDataWarehouseTest` |
| **P3.5** | `tests/Feature/RankTrackingTest.php` | • Mock Serper.dev trả về Top 100 kết quả Google Search.<br>• Kiểm tra phát hiện vị trí chính xác của website đích.<br>• Kiểm tra lưu vết `rank_tracking_history` theo ngày.<br>• Kiểm tra chặn khi vượt quá hạn mức từ khóa của gói cước. | `php artisan test --filter=RankTrackingTest` |
| **P3.6** | `tests/Feature/SeoIntelligenceTest.php` | • Đưa vào dữ liệu giả lập từ khóa tụt 5 bậc $\rightarrow$ Kiểm tra sinh cảnh báo `ranking_drop` mức `warning`.<br>• Đưa vào dữ liệu clicks giảm 30% $\rightarrow$ Sinh cảnh báo `traffic_decay`.<br>• Đưa vào 2 URL cùng ranking 1 query $\rightarrow$ Sinh cảnh báo `cannibalization`.<br>• Kiểm tra tính toán `Opportunity Score` chuẩn xác. | `php artisan test --filter=SeoIntelligenceTest` |
| **P3.7** | `tests/Feature/ContentRefreshOptimizationTest.php` | • Nhận đầu vào là bài viết cũ kèm gợi ý từ GSC.<br>• Mock AI sinh bản tối ưu (tiêu đề, meta, đề mục mới).<br>• Kiểm tra `SeoScoringEngine` chấm điểm trước và sau (điểm sau phải cao hơn).<br>• Kiểm tra bài viết gốc KHÔNG bị ghi đè.<br>• Kiểm tra chỉ cập nhật WordPress khi bấm nút duyệt. | `php artisan test --filter=ContentRefreshOptimizationTest` |
| **P3.8** | `tests/Feature/SeoAutomationSchedulerTest.php` | • Chạy command `seo:sync-all`, `seo:track-rankings`, `seo:detect-anomalies`.<br>• Kiểm tra cờ `withoutOverlapping(60)` ngăn chặn 2 process chạy đè.<br>• Kiểm tra sinh event `SeoAnomalyDetectedEvent` và lưu notification. | `php artisan test --filter=SeoAutomationSchedulerTest` |
| **P3.9** | `tests/Feature/ChatbotSeoAutomationToolTest.php` | • Người dùng chat: "Tháng này website có bao nhiêu click?".<br>• AI Orchestrator phân tích intent và gọi `GscToolAdapter`.<br>• Người dùng chat: "Cập nhật bài viết ID 15".<br>• Hệ thống tạo `ChatPendingAction` và trả về `ChatApprovalCard`. | `php artisan test --filter=ChatbotSeoAutomationToolTest` |
| **P3.10**| `tests/Feature/Phase3SecurityAuditTest.php`<br>`tests/Feature/EndToEndPhase3WorkflowTest.php` | • **Security:** 100% cô lập Multi-Tenant IDOR, Token Vault Encryption, Chặn SSRF loopback IP, Chống Prompt Injection.<br>• **E2E:** Chuỗi khép kín từ kết nối Google $\rightarrow$ Đồng bộ số liệu $\rightarrow$ Bắt cơ hội $\rightarrow$ Làm mới bài $\rightarrow$ Duyệt đăng WP $\rightarrow$ Kiểm toán Credit khớp 100%. | `php artisan test --filter=Phase3SecurityAuditTest`<br>`php artisan test --filter=EndToEndPhase3WorkflowTest` |

---

## 4. BỘ CODE MẪU MOCKING NGOẠI VI CHUẨN MỰC

Ví dụ chuẩn mực về cách giả lập Google Search Console API và Serper.dev trong Feature Test:

```php
// Mock Google Search Console Search Analytics Query
Http::fake([
    'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query*' => Http::response([
        'rows' => [
            [
                'keys' => ['2026-11-05', 'bọc răng sứ thẩm mỹ', 'https://nhakhoasmile.vn/boc-rang-su', 'VNM', 'DESKTOP'],
                'clicks' => 150,
                'impressions' => 2400,
                'ctr' => 0.0625,
                'position' => 3.2,
            ],
            [
                'keys' => ['2026-11-05', 'giá bọc răng sứ', 'https://nhakhoasmile.vn/boc-rang-su', 'VNM', 'DESKTOP'],
                'clicks' => 95,
                'impressions' => 1800,
                'ctr' => 0.0527,
                'position' => 4.8,
            ],
        ],
    ], 200),

    // Mock Serper.dev API
    'https://google.serper.dev/search' => Http::response([
        'searchParameters' => ['q' => 'bọc răng sứ thẩm mỹ', 'gl' => 'vn', 'hl' => 'vi'],
        'organic' => [
            ['title' => 'Top 1 Nha Khoa', 'link' => 'https://competitor1.vn', 'position' => 1],
            ['title' => 'Nha Khoa Smile Uy Tín', 'link' => 'https://nhakhoasmile.vn/boc-rang-su', 'position' => 2],
            ['title' => 'Top 3 Nha Khoa', 'link' => 'https://competitor2.vn', 'position' => 3],
        ],
    ], 200),
]);
```

---

## 5. BỘ LỆNH KIỂM CHỨNG TỰ ĐỘNG TOÀN DIỆN (FULL VERIFICATION SUITE)

Mỗi task trước khi trình Reviewer phải chạy bộ lệnh chuẩn mực sau:

```bash
# 1. Format code chuẩn PSR-12 của Laravel
cd backend && ./vendor/bin/pint --test

# 2. Chạy test suite của task tương ứng
php artisan test --filter={TaskTestClass}

# 3. Kiểm tra Next.js 16 build và type safety
cd ../frontend && npm run build
```

---
**Tài liệu được lập bởi:** Senior Software Architect  
**Trạng thái:** Sẵn sàng áp dụng cho khâu kiểm thử tự động của từng task Phase 3.
