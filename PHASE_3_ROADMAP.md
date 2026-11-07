# PHASE_3_ROADMAP.md — Lộ Trình Tổng Thể Giai Đoạn 3
## AuraSEO – SEO Automation & Intelligence (GSC, GA4, Rank Tracking & AI Content Optimization)

> **Dự án:** SaaS Viết Bài SEO Tự Động (Multi-Tenant SaaS)  
> **Kho mã nguồn:** `seoautomation.git`  
> **Nhánh mục tiêu:** `dev`  
> **Kiến trúc:** Laravel 13 (PHP 8.3) + Next.js 16 (React 19, TypeScript strict, Tailwind CSS v4) + MySQL 8 + Redis + Horizon  
> **Khung điều phối:** QLPK Multi-Agent Safe Autonomous Framework (Anti-Fake-Done Gate)  
> **Chế độ hiện tại:** ARCHITECTURE PLANNING & TASK DECOMPOSITION (Chưa được phép chỉnh sửa mã nguồn)  
> **Ngày lập kế hoạch:** 06/11/2026  

---

## 1. BỐI CẢNH & NGUYÊN TẮC CỐT LÕI

Hệ thống đã hoàn tất và nghiệm thu 100% qua 3 giai đoạn:
- **Phase 1 (TASK-01 $\rightarrow$ TASK-08):** Nền tảng SaaS, Multi-Tenancy, Credit Ledger ngân hàng, Cổng thanh toán, AI Pipeline.
- **Phase 2 (P2.1 $\rightarrow$ P2.9):** Nghiên cứu từ khóa, SERP Top 3 Benchmark (#1 Anchor), Topic Cluster, Content Gap, Content Studio, Chấm điểm SEO 100 điểm, Liên kết nội bộ, Tạo bài hàng loạt, Xuất bản WordPress.
- **Phase 2.5 (P2.5.1 $\rightarrow$ P2.5.9):** AI SEO Chatbot tương tác tự nhiên, Xử lý ảnh 3 tầng (AI Vision đọc hiểu ảnh người dùng ghim đúng H2/H3 $\rightarrow$ Stock thương mại miễn phí $\rightarrow$ AI Imagen 3 render; 100% WebP < 150KB), Cổng phê duyệt Human-in-the-Loop trước khi đăng bài.

Báo cáo kiểm toán [PRE_PHASE_3_AUDIT_REPORT.md](file:///Users/truongthinh/Documents/SaasAutoSeo/PRE_PHASE_3_AUDIT_REPORT.md) đã đánh giá kết luận: **READY WITH CONDITIONS**.

### 3 Điều Kiện Tiên Quyết Đã Thống Nhất:
1. **Google OAuth 2.0:** Sử dụng Google Cloud Web Application Credentials (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, Redirect URI).
2. **Rank Tracking Provider:** Sử dụng **Serper.dev API** (đã tích hợp trong Phase 2, chi phí $0.001/req, độ trễ < 800ms). **Nghiêm cấm tự cào Google SERP trực tiếp.**
3. **Human-in-the-Loop Content Refresh:** Mọi đề xuất tối ưu bài viết cũ bắt buộc phải có màn hình so sánh Trước/Sau (Diff Viewer) và yêu cầu người dùng xác nhận trước khi cập nhật lên WordPress.

### 12 Service Cốt Lõi Tái Sử Dụng 100%:
1. `App\Services\Billing\CreditLedgerService` (Zero-loss ledger, Reserve $\rightarrow$ Commit $\rightarrow$ Release).
2. `App\Services\SEO\SeoScoringEngine` (Chấm điểm 100 điểm thuần PHP, 0 credit).
3. `App\Services\Article\ContentStudioService` (AI Copilot assist, versioning, formatting).
4. `App\Services\Brand\BrandKnowledgeService` (Context enrichment theo Persona, Tone, Taboo words).
5. `App\Services\SEO\CompetitorAnalysisService` (SERP Top 3 benchmark đối chiếu).
6. `App\Services\SEO\InternalLinkingEngine` (Gợi ý liên kết nội bộ bài cũ trỏ bài mới).
7. `App\Services\WordPress\WordPressPublishService` (Mở rộng thêm method `updatePost`).
8. `App\Services\Chatbot\Tools\SeoToolRegistry` (Cầu nối đăng ký thêm 4 tool adapters mới).
9. `App\Services\Chatbot\ActionApprovalService` (Cổng xác nhận duyệt hành động nhạy cảm).
10. `App\Services\Security\AuditLogService` (Masking nhạy cảm token/secrets).
11. `App\Traits\BelongsToWorkspace` (Cô lập dữ liệu multi-tenant 100%).
12. `Recharts Analytics Components` (`AreaChart`, `BarChart`, bộ lọc ngày 7-30-90 ngày).

---

## 2. TỔNG QUAN 10 NHÓM TASK LỚN PHASE 3

```text
[P3.1: Integration Foundation] ───────┬────────────────────────┐
                                     │                        │
[P3.2: Google Search Console] ───────┤                        ▼
                                     ├─► [P3.4: SEO Data Warehouse] ──► [P3.6: SEO Intelligence]
[P3.3: Google Analytics 4] ──────────┘            │                               │
                                                  ▼                               ▼
[P3.5: Rank Tracking (Serper.dev)] ───────────────┴──────────────────► [P3.7: AI Content Refresh]
                                                                                  │
[P3.8: SEO Automation Engine] ◄───────────────────────────────────────────────────┤
                                                                                  │
[P3.9: Chatbot & Approval Integration] ◄──────────────────────────────────────────┘
                                │
                                ▼
[P3.10: Security, Performance & Final Acceptance Sign-off]
```

| Đợt | Mã Task | Tên Nhóm Task | Ưu Tiên | Phụ Thuộc | Trọng Tâm Nghiệp Vụ |
| :---: | :--- | :--- | :---: | :---: | :--- |
| **Đợt 1** | **P3.1** | Integration Foundation & Architecture | **P0** | Không | Chuẩn hóa OAuth Base, Token Vault mã hóa AES, DTOs và Quản lý kết nối. |
| **Đợt 2** | **P3.2** | Google OAuth 2.0 & Search Console | **P0** | P3.1 | Kết nối GSC, chọn Property, đồng bộ Clicks/Imp/CTR/Pos theo query/page/date. |
| **Đợt 3** | **P3.3** | Google Analytics 4 Integration | **P0** | P3.1 | Kết nối GA4, đồng bộ Sessions/Users/Engagement/Conversions theo landing page. |
| **Đợt 4** | **P3.4** | SEO Data Warehouse & Analytics Dashboard | **P0** | P3.2, P3.3 | Bảng lưu trữ ngày tối ưu Hash MD5, Job Queue định kỳ, Dashboard Recharts so sánh kỳ. |
| **Đợt 5** | **P3.5** | Rank Tracking với Serper.dev | **P0** | P3.1 | Quản lý từ khóa theo dõi, Job cào Top 100 Serper, lưu vết lịch sử vị trí theo ngày. |
| **Đợt 6** | **P3.6** | SEO Intelligence Engine | **P1** | P3.4, P3.5 | Thuật toán Heuristics: Phát hiện tụt hạng, CTR thấp, traffic decay, cannibalization. |
| **Đợt 7** | **P3.7** | AI Content Refresh & Optimization | **P0** | P3.4, P3.6 | Quy trình làm mới bài viết cũ: Diff Viewer Trước/Sau, chấm điểm SEO, duyệt WP. |
| **Đợt 8** | **P3.8** | SEO Automation Engine | **P1** | P3.4, P3.5, P3.6 | Scheduler với Distributed Lock `withoutOverlapping`, Cảnh báo in-app/email. |
| **Đợt 9** | **P3.9** | Chatbot & Human Approval Integration | **P0** | P3.4, P3.5, P3.7 | 4 Tool Adapters mới cho Chatbot, Thẻ duyệt cập nhật bài viết trong khung chat. |
| **Đợt 10**| **P3.10**| Security, Performance & Final Acceptance | **P0** | P3.1 $\rightarrow$ P3.9 | Test Suite cô lập Tenant, Token Security, Benchmark truy vấn và E2E khép kín. |

---

## 3. CÁC CỔNG NGHIỆM THU CHUYỂN GIAI ĐOẠN (EXECUTION GATES)

Mỗi đợt triển khai phải vượt qua 5 cổng kiểm định chất lượng:
1. **Gate 1 (Unit & Feature Test):** `php artisan test --filter=...` đạt `exit code = 0` (100% assertions pass, không skip).
2. **Gate 2 (Frontend Build):** `cd frontend && npm run build` compile thành công 34+ routes không có lỗi TypeScript strict.
3. **Gate 3 (Code Style):** `./vendor/bin/pint --test` tuân thủ hoàn toàn chuẩn PSR-12 của Laravel.
4. **Gate 4 (Tenant Boundary & Security):** Kiểm tra IDOR giữa 2 workspaces độc lập đạt 100% cô lập; token/secrets không bị rò rỉ.
5. **Gate 5 (Reviewer Sign-off):** Reviewer Agent (Gemini Argon / Claude) kiểm tra Git diff thực tế và cấp đánh giá `PASS` vào `review.md`.

---

## 4. BẢNG THEO DÕI TIẾN ĐỘ THỰC TẾ

Khi bắt đầu triển khai, trạng thái từng task sẽ được cập nhật đồng bộ vào `PROGRESS_P3.md`:
- `PENDING`: Đang chờ đến lượt theo phụ thuộc.
- `IN_PROGRESS`: Coder Agent đang lập trình trong danh sách `allowed_files`.
- `TESTING`: Đang chạy test tự động trên môi trường thật.
- `REVIEWING`: Độc lập thẩm định mã nguồn bởi Reviewer Agent.
- `DONE`: Đạt đủ 5 Execution Gates và commit lên Git.
- `FAILED / BLOCKED`: Lỗi cần khắc phục trước khi tiếp tục.

---
**Tài liệu này được lập bởi:** Senior Software Architect  
**Phê duyệt đưa vào thực thi:** Sẵn sàng kết hợp với `PHASE_3_TASK_BREAKDOWN.md`.
