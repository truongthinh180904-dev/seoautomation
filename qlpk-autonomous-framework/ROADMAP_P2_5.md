# ROADMAP_P2_5.md — Đặc Tả Chi Tiết 9 Nhóm Task Giai Đoạn Bổ Sung (Phase 2.5)
## AI SEO CHATBOT & CONVERSATIONAL CONTENT AUTOMATION

> **Dự án:** SaaS Viết Bài SEO Tự Động (Multi-Tenant SaaS)  
> **Tech Stack:** Laravel 13 (PHP 8.3) + Next.js 16 (React 19, Tailwind CSS v4, shadcn/ui)  
> **Bộ điều phối:** QLPK Multi-Agent Safe Autonomous Framework (Anti-Fake-Done Gate)  
> **Nguyên tắc vàng:** **TÁI SỬ DỤNG 100% CÁC MODULE PHASE 1 & 2** — Không viết lại các service SEO, không làm vỡ các tính năng đã ổn định.

---

## Bảng Tổng Hợp Thứ Tự Triển Khai (Phụ Thuộc & Cổng Nghiệm Thu)

```text
[Đợt 1: TASK P2.5.1 (Chatbot Interface - Next.js UI)]
        │
        ▼
[Đợt 2: TASK P2.5.2 (Conversation Engine - DB & Session Context)]
        │
        ▼
[Đợt 3: TASK P2.5.3 (AI Orchestrator - Intent & Planner)]
        │
        ▼
[Đợt 4: TASK P2.5.4 (Tool Calling - Bridge to Phase 1 & 2 Services)]
        │
        ▼
[Đợt 5: TASK P2.5.5 (Content Interaction - In-Chat Editing & Rewrite)]
        │
        ▼
[Đợt 6: TASK P2.5.6 (Task & Progress Streaming - Realtime Status)]
        │
        ▼
[Đợt 7: TASK P2.5.7 (Approval & Action Control - Interactive Gate)]
        │
        ▼
[Đợt 8: TASK P2.5.8 (Credit & Cost Integration - Zero Loss Ledger)]
        │
        ▼
[Đợt 9: TASK P2.5.9 (Security Audit, E2E Test Suite & Reviewer Sign-off)]
```

---

## CHI TIẾT 9 NHÓM TASK GIAI ĐOẠN 2.5

### [NHÓM 1] TASK P2.5.1: Chatbot Interface (Giao Diện Trò Chuyện Next.js 16)
* **Ưu tiên:** P0 — Khởi tạo giao diện tương tác người dùng.
* **Mục tiêu:** Xây dựng giao diện Chatbot hiện đại, hỗ trợ chia cột (Lịch sử hội thoại bên trái, Khung chat trung tâm, Widget trạng thái tác vụ bên phải), hỗ trợ chuyển đổi Workspace & Website, và hỗ trợ tải đính kèm hình ảnh trực tiếp.
* **Công việc chi tiết:**
  - `P2.5.1.1`: Route `/chatbot` trong App Router của Next.js 16.
  - `P2.5.1.2`: Component `ChatSidebar`: Danh sách cuộc trò chuyện cũ, nút "Tạo cuộc trò chuyện mới", ghim hội thoại.
  - `P2.5.1.3`: Component `ChatMessageList`: Hiển thị tin nhắn người dùng và phản hồi AI với định dạng Rich Markdown, khối code, bảng dữ liệu, ảnh đính kèm và thẻ tác vụ.
  - `P2.5.1.4`: Component `ChatInputBox`: Ô nhập văn bản đa dòng tự co giãn, nút đính kèm/kéo thả hình ảnh (`ChatImageUploadDropzone`), gợi ý câu lệnh nhanh (Quick Prompts: "Nghiên cứu từ khóa", "Phân tích đối thủ", "Viết bài SEO", "Chấm điểm bài"), nút gửi có trạng thái loading.
  - `P2.5.1.5`: Bổ sung đường dẫn "Aura Assistant" vào Sidebar chính của Dashboard.
* **Phụ thuộc:** Không.
* **Tệp cho phép tác động (`allowed_files`):**
  - `frontend/app/(dashboard)/chatbot/page.tsx`
  - `frontend/components/features/chatbot/ChatSidebar.tsx`
  - `frontend/components/features/chatbot/ChatMessageList.tsx`
  - `frontend/components/features/chatbot/ChatMessageItem.tsx`
  - `frontend/components/features/chatbot/ChatInputBox.tsx`
  - `frontend/components/features/chatbot/ChatImageUploadDropzone.tsx`
  - `frontend/components/features/chatbot/QuickPromptPills.tsx`
  - `frontend/components/layouts/Sidebar/index.tsx`
  - `frontend/lib/constants/routes.ts`
* **Lệnh kiểm thử:** `cd frontend && npm run build`
* **Tiêu chuẩn nghiệm thu:** Route `/chatbot` được tạo thành công, hỗ trợ upload ảnh xem trước mượt mà, giao diện responsive, build Next.js 16 hoàn tất không có lỗi TypeScript hay Linting.

---

### [NHÓM 2] TASK P2.5.2: Conversation Engine (Cơ Sở Dữ Liệu & Phiên Hội Thoại)
* **Ưu tiên:** P0 — Nền tảng dữ liệu cho hội thoại.
* **Mục tiêu:** Tạo cơ chế lưu trữ phiên hội thoại (conversations) và tin nhắn (messages), hỗ trợ phân tách nghiêm ngặt theo Workspace, Website và User ID, có tóm tắt ngữ cảnh (Context Memory) để tránh tràn token.
* **Công việc chi tiết:**
  - `P2.5.2.1`: Migration tạo bảng `conversations` và `conversation_messages` với các index và khóa ngoại an toàn.
  - `P2.5.2.2`: Model `Conversation` và `ConversationMessage` áp dụng trait `BelongsToWorkspace`.
  - `P2.5.2.3`: `ConversationService`: CRUD phiên chat, lưu tin nhắn, tải lịch sử 10 tin gần nhất kèm ngữ cảnh tóm tắt.
  - `P2.5.2.4`: `ConversationController`: RESTful API `/api/v1/conversations`, `/api/v1/conversations/{uuid}/messages`.
  - `P2.5.2.5`: Unit & Feature Test: `ConversationEngineTest.php`.
* **Phụ thuộc:** P2.5.1.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/database/migrations/*_create_conversations_and_messages_tables.php`
  - `backend/app/Models/Conversation.php`
  - `backend/app/Models/ConversationMessage.php`
  - `backend/app/Services/Chatbot/ConversationService.php`
  - `backend/app/Http/Controllers/Api/V1/Chatbot/ConversationController.php`
  - `backend/app/Http/Requests/Chatbot/StoreConversationRequest.php`
  - `backend/app/Http/Requests/Chatbot/SendMessageRequest.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/ConversationEngineTest.php`
  - `frontend/hooks/useChatbot.ts`
* **Lệnh kiểm thử:** `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=ConversationEngineTest`
* **Tiêu chuẩn nghiệm thu:** API tạo và lấy tin nhắn hoạt động hoàn hảo, kiểm tra cô lập Workspace A không thể đọc chat của Workspace B đạt 100%.

---

### [NHÓM 3] TASK P2.5.3: AI Orchestrator (Bộ Phân Tích Ý Định & Lập Kế Hoạch)
* **Ưu tiên:** P0 — Bộ não của Chatbot.
* **Mục tiêu:** Xây dựng Engine phân tích yêu cầu ngôn ngữ tự nhiên của khách hàng, trích xuất ý định (Intent), lập kế hoạch thực thi nhiều bước (Execution Plan), và tự động đặt câu hỏi làm rõ nếu người dùng nhập thiếu thông tin.
* **Công việc chi tiết:**
  - `P2.5.3.1`: `AiOrchestratorService`: Xây dựng System Prompt định hình vai trò Senior SEO Specialist, nhúng danh sách các Tools và mô tả khả năng.
  - `P2.5.3.2`: `IntentParserService`: Phân tích câu nói người dùng thành `UserIntentDTO` (hành động mục tiêu, tham số đi kèm, độ tự tin).
  - `P2.5.3.3`: Xử lý trường hợp thiếu thông tin: Nếu người dùng chỉ nói *"Viết bài đi"*, AI sẽ lịch sự hỏi lại: *"Bạn muốn viết về từ khóa nào? Đã có dàn ý chưa hay để mình lập dàn ý giúp bạn?"*.
  - `P2.5.3.4`: Tự động nạp kiến thức thương hiệu (`BrandKnowledgeBase`) của Website hiện tại vào ngữ cảnh.
  - `P2.5.3.5`: Feature Test: `AiOrchestratorTest.php`.
* **Phụ thuộc:** P2.5.2.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/app/Services/Chatbot/AiOrchestratorService.php`
  - `backend/app/Services/Chatbot/IntentParserService.php`
  - `backend/app/DTOs/Chatbot/UserIntentDTO.php`
  - `backend/app/DTOs/Chatbot/ExecutionPlanDTO.php`
  - `backend/tests/Feature/AiOrchestratorTest.php`
* **Lệnh kiểm thử:** `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=AiOrchestratorTest`
* **Tiêu chuẩn nghiệm thu:** Nhận diện chính xác 8 ý định chính (nghiên cứu từ khóa, cào đối thủ, lập cluster, viết bài, viết lại, chấm điểm, bulk, publish), xử lý khéo léo yêu cầu mơ hồ.

---

### [NHÓM 4] TASK P2.5.4: Tool Calling Integration (Cầu Nối 10 Công Cụ SEO Sẵn Có & Tối Ưu Top 3 Đối Thủ)
* **Ưu tiên:** P0 — Khả năng hành động thực tế.
* **Mục tiêu:** Xây dựng cơ chế gọi công cụ (Tool Calling/Execution) kết nối toàn bộ 10 Services sẵn có từ Phase 1 và Phase 2; tối ưu phân tích **chỉ tập trung Top 3 đối thủ** (lấy đối thủ #1 làm mỏ neo chuẩn xác); và tích hợp công cụ tìm kiếm ảnh không bản quyền kèm tự động nén WebP.
* **Công việc chi tiết:**
  - `P2.5.4.1`: `SeoToolRegistry`: Đăng ký 10 công cụ chuẩn hóa (`keyword_research`, `competitor_analysis`, `topic_cluster`, `content_gap`, `content_studio_write`, `seo_score`, `internal_links`, `ai_image`, `stock_image_free`, `bulk_generation`, `wordpress_publish`).
  - `P2.5.4.2`: `CompetitorAnalysisAdapter`: Cấu hình bộ lọc chuyên sâu **chỉ lấy Top 3 đối thủ Google SERP**, lấy đối thủ xếp hạng #1 làm thước đo chính (Anchor Benchmark) về số từ, mật độ thực thể LSI và cấu trúc thẻ Heading để bài viết vượt trội chính xác đối thủ đứng đầu.
  - `P2.5.4.3`: `StockImageToolAdapter` & `ImageOptimizationService`: Tìm kiếm ảnh stock thương mại **100% không bản quyền** từ các nền tảng ảnh lớn (Unsplash, Pexels API); tự động chuyển đổi sang định dạng WebP, nén dung lượng dưới 150KB bằng thư viện GD để tối đa tốc độ tải trang.
  - `P2.5.4.4`: Xây dựng các Tool Adapters còn lại gọi trực tiếp vào `KeywordResearchService`, `TopicClusterService`, `SeoScoringEngine`, `BulkGenerationService`, `WordPressPublishService`.
  - `P2.5.4.5`: Feature Test: `SeoToolCallingTest.php`.
* **Phụ thuộc:** P2.5.3.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/app/Services/Chatbot/Tools/SeoToolRegistry.php`
  - `backend/app/Services/Chatbot/Tools/Contracts/ChatToolInterface.php`
  - `backend/app/Services/Chatbot/Tools/Adapters/*.php`
  - `backend/tests/Feature/SeoToolCallingTest.php`
* **Lệnh kiểm thử:** `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=SeoToolCallingTest`
* **Tiêu chuẩn nghiệm thu:** Cả 10 công cụ đều được gọi thành công, phân tích đối thủ tập trung đúng Top 3 (lấy #1 làm chuẩn), ảnh stock tìm được cam kết không dính bản quyền và nén WebP dưới 150KB.

---

### [NHÓM 5] TASK P2.5.5: Content Interaction & Vision-Powered Media (Biên Tập & Xử Lý Ảnh 3 Tầng Chuẩn SEO)
* **Ưu tiên:** P1 — Tương tác trực tiếp trên bài viết & Xử lý ảnh đa phương thức theo thứ tự ưu tiên nghiêm ngặt.
* **Mục tiêu:** Cho phép người dùng xem bài viết nháp ngay trong khung chat, chọn đoạn văn để AI viết lại/mở rộng; đồng thời thực thi **Chiến lược xử lý hình ảnh 3 tầng chuẩn SEO**:
  1. **Ưu tiên số 1 (Quan trọng nhất):** AI Gemini Vision bắt buộc phải **đọc hiểu ý nghĩa ảnh người dùng gửi kèm** (nhận diện chủ thể, sản phẩm, bối cảnh) và **tự động đưa hình ảnh đó vào đúng vị trí H2/H3 phù hợp nhất trên website** kèm thẻ ALT và caption chuẩn SEO.
  2. **Ưu tiên số 2 (Nếu người dùng không gửi ảnh):** Tự động tìm kiếm ảnh stock chất lượng cao, **100% không dính bản quyền thương mại** từ các nền tảng ảnh lớn (Unsplash, Pexels API) theo từ khóa H2, và tự động nén nhỏ sang WebP dưới 150KB.
  3. **Ưu tiên số 3 (Nếu nền tảng ảnh không có ảnh phù hợp):** AI Gemini (Imagen 3) tự động **render/sinh ra hình ảnh chuẩn xác** theo đúng ngữ cảnh H2, sau đó nén nhỏ sang WebP và chèn thẻ ALT chuẩn SEO.
* **Công việc chi tiết:**
  - `P2.5.5.1`: `InChatContentService`: Điều phối các tác vụ biên tập nội dung đoạn văn từ hội thoại (kế thừa `ContentStudioService`).
  - `P2.5.5.2`: `ChatImageVisionService`: Khi người dùng tải ảnh lên khung chat, AI Gemini Vision đọc hiểu nội dung bức ảnh (sản phẩm gì, bối cảnh nào, ý nghĩa gì), đối chiếu với dàn ý bài viết và tự động ghim vào mục H2/H3 thích hợp nhất kèm thẻ ALT và chú thích chuẩn SEO.
  - `P2.5.5.3`: `ImageFallbackPipeline`: Thực thi quy trình thác nước (Waterfall): Nếu không có ảnh người dùng $\rightarrow$ Quét kho ảnh Pexels/Unsplash không bản quyền $\rightarrow$ Nếu không có ảnh phù hợp $\rightarrow$ Gọi AI Gemini render ảnh $\rightarrow$ Toàn bộ ảnh đều qua bộ nén GD chuyển sang WebP < 150KB.
  - `P2.5.5.4`: Thẻ hiển thị bài viết `InChatArticleCard`: Hiển thị bài viết kèm hình ảnh đã nén và gắn vị trí chuẩn xác, nút copy, nút mở sang Content Studio.
  - `P2.5.5.5`: Feature Test: `InChatContentInteractionTest.php`.
* **Phụ thuộc:** P2.5.4.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/app/Services/Chatbot/InChatContentService.php`
  - `backend/app/Services/Chatbot/ChatImageVisionService.php`
  - `backend/app/Services/Chatbot/ImageFallbackPipeline.php`
  - `backend/app/Http/Controllers/Api/V1/Chatbot/ChatContentController.php`
  - `backend/tests/Feature/InChatContentInteractionTest.php`
  - `frontend/components/features/chatbot/InChatArticleCard.tsx`
  - `frontend/components/features/chatbot/InChatInlineToolbar.tsx`
* **Lệnh kiểm thử:** `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=InChatContentInteractionTest && cd ../frontend && npm run build`
* **Tiêu chuẩn nghiệm thu:** Bài viết hiển thị đẹp mắt trong chat; ảnh người dùng gửi được AI Gemini Vision nhận diện chính xác và gắn đúng vào phần H2/H3 phù hợp nhất; trường hợp không có ảnh tự lấy ảnh stock không bản quyền hoặc AI Gemini render; 100% ảnh xuất hiện trên website đều được nén nhỏ WebP và chuẩn SEO thẻ ALT.

---

### [NHÓM 6] TASK P2.5.6: Task & Progress Streaming (Theo Dõi Tiến Trình Thời Gian Thực)
* **Ưu tiên:** P1 — Trải nghiệm phản hồi trực quan.
* **Mục tiêu:** Đối với các tác vụ dài (nghiên cứu 50 từ khóa, viết bài 3000 từ, tạo 20 bài hàng loạt), hệ thống không giữ kết nối HTTP mà đẩy vào Queue và phát trạng thái tiến độ thời gian thực (0% $\rightarrow$ 100%) vào khung chat.
* **Công việc chi tiết:**
  - `P2.5.6.1`: API Controller `ChatStreamController`: Cung cấp endpoint polling / Server-Sent Events (SSE) theo dõi trạng thái `Task` qua UUID.
  - `P2.5.6.2`: Component `ChatTaskProgressWidget`: Hiển thị thanh tiến trình trực quan, tên bước đang chạy (ví dụ: *"Đang phân tích dàn ý...", "Đang tải ảnh minh họa...", "Đang kiểm tra SEO..."*).
  - `P2.5.6.3`: Hỗ trợ tự động tiếp tục (Resume) hoặc hiển thị thông báo lỗi rõ ràng nếu tác vụ gặp sự cố.
  - `P2.5.6.4`: Feature Test: `ChatTaskProgressTest.php`.
* **Phụ thuộc:** P2.5.4.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/app/Http/Controllers/Api/V1/Chatbot/ChatStreamController.php`
  - `backend/tests/Feature/ChatTaskProgressTest.php`
  - `frontend/components/features/chatbot/ChatTaskProgressWidget.tsx`
* **Lệnh kiểm thử:** `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=ChatTaskProgressTest`
* **Tiêu chuẩn nghiệm thu:** Thanh tiến trình cập nhật mượt mà từ 0% đến 100%, không bị treo UI, xử lý lỗi an toàn.

---

### [NHÓM 7] TASK P2.5.7: Approval & Action Control (Cổng Xác Nhận Hành Động Nhạy Cảm)
* **Ưu tiên:** P0 — An toàn dữ liệu & Chống thất thoát.
* **Mục tiêu:** Bắt buộc có sự xác nhận của người dùng (Human-in-the-Loop) trước khi thực hiện các hành động nhạy cảm như: Đăng bài lên WordPress, chạy lô Bulk bài viết lớn, hoặc tiêu tốn credit đáng kể.
* **Công việc chi tiết:**
  - `P2.5.7.1`: Migration tạo bảng `chat_pending_actions`.
  - `P2.5.7.2`: `ActionApprovalService`: Tạo hành động chờ duyệt, lưu payload, định giá credit dự kiến, tự động hết hạn sau 30 phút.
  - `P2.5.7.3`: `ActionApprovalController`: Endpoint `/api/v1/chatbot/actions/{uuid}/approve` và `/reject`.
  - `P2.5.7.4`: Component `ChatApprovalCard`: Thẻ xác nhận tương tác trong chat với thông tin rõ ràng: Website đích, Số credit dự kiến, Nút [Xác nhận thực hiện] và [Hủy bỏ].
  - `P2.5.7.5`: Feature Test: `ActionApprovalTest.php`.
* **Phụ thuộc:** P2.5.4.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/database/migrations/*_create_chat_pending_actions_table.php`
  - `backend/app/Models/ChatPendingAction.php`
  - `backend/app/Services/Chatbot/ActionApprovalService.php`
  - `backend/app/Http/Controllers/Api/V1/Chatbot/ActionApprovalController.php`
  - `backend/tests/Feature/ActionApprovalTest.php`
  - `frontend/components/features/chatbot/ChatApprovalCard.tsx`
* **Lệnh kiểm thử:** `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=ActionApprovalTest`
* **Tiêu chuẩn nghiệm thu:** AI tuyệt đối không tự ý đăng bài hoặc trừ credit lớn khi người dùng chưa nhấn nút Phê duyệt trên thẻ xác nhận.

---

### [NHÓM 8] TASK P2.5.8: Credit & Cost Integration (Tích Hợp Sổ Cái Credit & Chi Phí Token)
* **Ưu tiên:** P0 — Bảo vệ tài chính nền tảng.
* **Mục tiêu:** Kết nối chặt chẽ với `CreditLedgerService` hiện tại, đảm bảo mọi hành động gọi Tool đều được tính credit minh bạch, ghi nhận token, model và chi phí USD vào `ai_usage_logs`, chống trừ credit 2 lần.
* **Công việc chi tiết:**
  - `P2.5.8.1`: Tích hợp hàm `reserveCredits()` và `commitReservation()` trước và sau khi Chatbot gọi bất kỳ Tool nào có tính phí.
  - `P2.5.8.2`: Đảm bảo quy tắc **Zero Loss Guarantee**: Nếu Tool bị lỗi hoặc người dùng hủy hành động, toàn bộ credit giữ trước được hoàn lại 100%.
  - `P2.5.8.3`: Ghi log chi tiết số token prompt/completion, latency và chi phí USD thực tế của phiên chat.
  - `P2.5.8.4`: Báo lỗi HTTP 402 `InsufficientCreditsException` nếu Workspace không đủ số dư credit.
  - `P2.5.8.5`: Feature Test: `ChatbotCreditCostTest.php`.
* **Phụ thuộc:** P2.5.7.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/app/Services/Chatbot/ChatbotBillingBridge.php`
  - `backend/tests/Feature/ChatbotCreditCostTest.php`
* **Lệnh kiểm thử:** `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=ChatbotCreditCostTest`
* **Tiêu chuẩn nghiệm thu:** Không có tình trạng trừ lặp, số dư ví khớp 100% với sổ cái giao dịch `credit_transactions`.

---

### [NHÓM 9] TASK P2.5.9: Security Audit, E2E Test Suite & Reviewer Sign-Off
* **Ưu tiên:** P0 — Cổng nghiệm thu toàn diện Giai đoạn 2.5.
* **Mục tiêu:** Kiểm tra toàn diện an ninh multi-tenant, chống prompt injection, chống leo thang đặc quyền công cụ, thực thi bộ test End-to-End hoàn chỉnh từ trò chuyện đến xuất bản bài viết lên WordPress, và xuất báo cáo nghiệm thu chính thức.
* **Công việc chi tiết:**
  - `P2.5.9.1`: Kiểm thử Multi-Tenancy: Xác minh Workspace A không thể xem chat, gọi tool, hoặc xem bài viết của Workspace B qua Chatbot.
  - `P2.5.9.2`: Kiểm thử Prompt Injection: Thử nghiệm các prompt tấn công bẻ khóa (jailbreak) và xác nhận Chatbot từ chối an toàn.
  - `P2.5.9.3`: Test Suite End-to-End: `EndToEndChatbotWorkflowTest.php` (Luồng đầy đủ: Người dùng yêu cầu $\rightarrow$ Lập dàn ý $\rightarrow$ Viết bài $\rightarrow$ Chấm điểm SEO $\rightarrow$ Xác nhận duyệt $\rightarrow$ Đăng WordPress $\rightarrow$ Trừ credit chuẩn).
  - `P2.5.9.4`: Chạy toàn bộ test suite dự án (`php artisan test`) và build Next.js 16 (`npm run build`).
  - `P2.5.9.5`: Xuất bản tài liệu nghiệm thu tổng kết: `PHASE_2_5_COMPLETION_REPORT.md`.
* **Phụ thuộc:** Tất cả các task từ P2.5.1 đến P2.5.8.
* **Tệp cho phép tác động (`allowed_files`):**
  - `backend/tests/Feature/EndToEndChatbotWorkflowTest.php`
  - `backend/tests/Feature/ChatbotSecurityAuditTest.php`
  - `qlpk-autonomous-framework/reports/tasks/PHASE_2_5_COMPLETION_REPORT.md`
  - `qlpk-autonomous-framework/PROGRESS_P2_5.md`
* **Lệnh kiểm thử:** `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test && cd ../frontend && npm run build`
* **Tiêu chuẩn nghiệm thu:** 100% tests PASSED (exit code 0), build Next.js 16 không lỗi, Reviewer độc lập (Gemini 4 Argon) cấp verdict PASS 14/14 tiêu chí.

---
