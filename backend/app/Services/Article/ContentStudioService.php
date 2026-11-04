<?php

namespace App\Services\Article;

use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIProviderService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Brand\BrandKnowledgeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ContentStudioService
{
    public function __construct(
        protected CreditLedgerService $creditLedger,
        protected ?AIProviderService $aiProvider = null,
        protected ?BrandKnowledgeService $brandKnowledge = null,
        protected ?ArticleContentFormatter $formatter = null
    ) {
        $this->formatter = $this->formatter ?? (app()->bound(ArticleContentFormatter::class) ? app(ArticleContentFormatter::class) : new ArticleContentFormatter);
    }

    /**
     * Lấy instance AIProviderService (hỗ trợ resolve động qua container khi mock trong tests).
     */
    public function getAiProvider(): ?AIProviderService
    {
        if ($this->aiProvider !== null) {
            return $this->aiProvider;
        }

        if (app()->bound(AIProviderService::class)) {
            return app(AIProviderService::class);
        }

        return null;
    }

    /**
     * Lấy instance BrandKnowledgeService (hỗ trợ resolve động qua container).
     */
    public function getBrandKnowledge(): ?BrandKnowledgeService
    {
        if ($this->brandKnowledge !== null) {
            return $this->brandKnowledge;
        }

        if (app()->bound(BrandKnowledgeService::class)) {
            return app(BrandKnowledgeService::class);
        }

        try {
            return app(BrandKnowledgeService::class);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Tải dữ liệu toàn diện cho phiên làm việc Content Studio.
     *
     * @return array<string, mixed>
     */
    public function getStudioData(Article $article, Workspace $workspace): array
    {
        $this->assertWorkspaceOwnership($article, $workspace);

        $article->loadMissing([
            'website:id,name,domain,url',
            'keyword:id,keyword',
            'user:id,name,email',
        ]);

        /** @var Collection $recentVersions */
        $recentVersions = $article->versions()
            ->with(['changedBy:id,name,email'])
            ->orderByDesc('version_number')
            ->limit(10)
            ->get();

        $content = (string) ($article->content ?? '');
        $wordCount = (int) ($article->word_count ?: $this->formatter->wordCount($content));

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'content' => $content,
            'outline' => $article->outline ?? [],
            'seo_title' => $article->seo_title,
            'seo_description' => $article->seo_description,
            'focus_keyword' => $article->focus_keyword,
            'word_count' => $wordCount,
            'seo_score' => $article->seo_score,
            'readability_score' => $article->readability_score,
            'status' => $article->status instanceof ArticleStatus ? $article->status->value : (string) $article->status,
            'website' => $article->website ? [
                'id' => $article->website->id,
                'name' => $article->website->name,
                'domain' => $article->website->domain,
                'url' => $article->website->url,
            ] : null,
            'keyword' => $article->keyword ? [
                'id' => $article->keyword->id,
                'keyword' => $article->keyword->keyword,
            ] : null,
            'recent_versions' => $recentVersions,
            'last_saved_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Xử lý tác vụ AI Copilot trên đoạn văn bản (Rewrite, Expand, Shorten, Change Tone, v.v.).
     * Trừ chính xác 1 credit trên mỗi lượt gọi qua CreditLedgerService.
     *
     * @param  array{tone?: ?string, instruction?: ?string, context_before?: ?string, context_after?: ?string, idempotency_key?: ?string}  $options
     * @return array<string, mixed>
     */
    public function assist(
        Article $article,
        Workspace $workspace,
        string $action,
        string $selectedText,
        array $options = [],
        ?User $actor = null
    ): array {
        $this->assertWorkspaceOwnership($article, $workspace);

        $selectedText = trim($selectedText);
        if ($selectedText === '') {
            throw new InvalidArgumentException('Đoạn văn bản được chọn không được để trống.');
        }

        // 1. Trừ credit: 1 credit / yêu cầu AI Copilot
        $creditCost = (int) config('ai_costs.credits.studio_ai_copilot_call', 1);
        $idempotencyKey = $options['idempotency_key'] ?? ('studio_assist:'.$workspace->id.':'.$article->id.':'.Str::uuid()->toString());

        $this->creditLedger->deductCredits(
            workspace: $workspace,
            amount: $creditCost,
            referenceType: 'studio_ai_copilot',
            referenceId: $article->id,
            idempotencyKey: $idempotencyKey,
            metadata: [
                'action' => $action,
                'article_id' => $article->id,
                'text_length' => mb_strlen($selectedText),
            ],
            fromReserved: false,
            actor: $actor
        );

        // 2. Tích hợp Brand Knowledge Base của website (nếu có)
        $brandContext = '';
        $brandKnowledge = $this->getBrandKnowledge();
        if ($brandKnowledge && $article->website_id) {
            $brandKnowledgeId = isset($article->brand_knowledge_id) ? $article->brand_knowledge_id : null;
            $brandContext = $brandKnowledge->buildPromptContext($article->website_id, $brandKnowledgeId);
        }

        // 3. Xây dựng System Prompt & Instructions
        $systemPrompt = "You are an elite SEO editor and AI writing copilot for high-ranking content.\n"
            ."Your objective is to refine, rewrite, or expand the user's selected text according to their requested action.\n"
            ."Strict instructions:\n"
            ."1. Output ONLY the resulting revised text directly. Do not include markdown code blocks, conversational introductions, or commentary.\n"
            ."2. Preserve factual accuracy and terminology unless explicitly instructed otherwise.\n"
            ."3. Maintain high readability, natural tone, and SEO relevance.\n"
            ."4. Format cleanly using HTML tags (<p>, <strong>, <em>, <ul>, <ol>, <li>) or plain text matching the input format.\n"
            .($brandContext !== '' ? "\n".$brandContext : '');

        $actionInstructions = [
            'rewrite' => 'Viết lại đoạn văn sau sao cho cuốn hút, mượt mà và tự nhiên hơn, giữ nguyên ý nghĩa cốt lõi.',
            'expand' => 'Mở rộng đoạn văn sau với các luận điểm sâu sắc hơn, dẫn chứng hoặc giải thích chi tiết, duy trì mạch văn tự nhiên.',
            'shorten' => 'Rút gọn đoạn văn sau thật súc tích, cô đọng, loại bỏ các từ ngữ thừa nhưng vẫn giữ đầy đủ thông điệp chính.',
            'change_tone' => 'Chuyển đổi phong cách giọng văn của đoạn sau sang giọng: '.($options['tone'] ?? 'chuyên nghiệp (professional)').'.',
            'add_example' => 'Bổ sung một ví dụ thực tế hoặc case study ngắn gọn, trực quan để minh họa rõ cho ý tưởng trong đoạn văn sau.',
            'fix_grammar' => 'Kiểm tra và sửa toàn bộ lỗi chính tả, dấu câu, ngữ pháp và cấu trúc câu trong đoạn sau để đạt chuẩn văn phong xuất bản.',
            'custom' => ! empty($options['instruction']) ? (string) $options['instruction'] : 'Cải thiện đoạn văn bản sau một cách chuyên nghiệp.',
        ];

        $instruction = $actionInstructions[$action] ?? $actionInstructions['rewrite'];

        $userPrompt = "Hành động yêu cầu: {$instruction}\n";
        if (! empty($options['tone']) && $action !== 'change_tone') {
            $userPrompt .= "Giọng văn mong muốn: {$options['tone']}\n";
        }
        if (! empty($options['instruction']) && $action !== 'custom') {
            $userPrompt .= "Yêu cầu bổ sung: {$options['instruction']}\n";
        }
        if (! empty($options['context_before'])) {
            $userPrompt .= "Ngữ cảnh đoạn trước:\n\"\"\"{$options['context_before']}\"\"\"\n\n";
        }
        $userPrompt .= "Đoạn văn bản cần xử lý:\n\"\"\"{$selectedText}\"\"\"\n\n";
        if (! empty($options['context_after'])) {
            $userPrompt .= "Ngữ cảnh đoạn sau:\n\"\"\"{$options['context_after']}\"\"\"\n\n";
        }
        $userPrompt .= 'Hãy trả về trực tiếp kết quả đã xử lý (chỉ trả nội dung, không thêm bất kỳ lời dẫn nào).';

        // 4. Gọi LLM Provider
        $resultText = '';
        $aiProvider = $this->getAiProvider();
        if ($aiProvider !== null) {
            $aiRequest = new AIRequestDTO(
                systemPrompt: $systemPrompt,
                userPrompt: $userPrompt,
                model: 'gemini-2.5-flash',
                agentType: AgentType::WRITING,
                tenantId: $article->tenant_id,
                maxTokens: 2000,
                temperature: 0.5,
                articleId: $article->id,
                workspaceId: $workspace->id
            );

            $aiResponse = $aiProvider->complete($aiRequest);
            $resultText = trim($aiResponse->content);
        } else {
            $resultText = $this->generateLocalFallback($action, $selectedText, $options);
        }

        // Loại bỏ code fences nếu AI bọc ```html ... ```
        $resultText = preg_replace('/^```(?:html)?\s*/i', '', $resultText) ?? $resultText;
        $resultText = preg_replace('/\s*```$/', '', $resultText) ?? $resultText;
        $resultText = trim($resultText);

        return [
            'action' => $action,
            'original_text' => $selectedText,
            'result_text' => $resultText,
            'credits_deducted' => $creditCost,
            'word_count' => $this->formatter->wordCount($resultText),
        ];
    }

    /**
     * Tự động lưu nháp nội dung bài viết và quản lý lịch sử phiên bản.
     *
     * @param  array{title?: ?string, content?: ?string, outline?: mixed, seo_title?: ?string, seo_description?: ?string, create_version?: ?bool, version_reason?: ?string}  $data
     * @return array<string, mixed>
     */
    public function autosave(Article $article, Workspace $workspace, array $data, ?User $actor = null): array
    {
        $this->assertWorkspaceOwnership($article, $workspace);

        return DB::transaction(function () use ($article, $data, $actor) {
            if (array_key_exists('title', $data) && $data['title'] !== null) {
                $article->title = trim((string) $data['title']);
            }

            if (array_key_exists('content', $data) && $data['content'] !== null) {
                $newContent = (string) $data['content'];
                $article->content = $newContent;
                $article->word_count = $this->formatter->wordCount($newContent);
                $article->duplicate_check_hash = $article->duplicateCheckHash($newContent);
            }

            if (array_key_exists('outline', $data)) {
                $article->outline = is_array($data['outline']) ? $data['outline'] : json_decode((string) $data['outline'], true);
            }

            if (array_key_exists('seo_title', $data) && $data['seo_title'] !== null) {
                $article->seo_title = trim((string) $data['seo_title']);
            }

            if (array_key_exists('seo_description', $data) && $data['seo_description'] !== null) {
                $article->seo_description = trim((string) $data['seo_description']);
            }

            $article->save();

            $version = null;
            if (! empty($data['create_version'])) {
                $versionReason = ! empty($data['version_reason'])
                    ? (string) $data['version_reason']
                    : 'Lưu phiên bản từ Content Studio';

                /** @var User $versionActor */
                $versionActor = $actor ?? $article->user ?? User::where('tenant_id', $article->tenant_id)->first();

                if ($versionActor) {
                    $version = $article->createVersion([
                        'title' => $article->title,
                        'content' => $article->content ?? '',
                        'seo_title' => $article->seo_title,
                        'seo_description' => $article->seo_description,
                        'word_count' => (int) $article->word_count,
                        'seo_score' => (int) $article->seo_score,
                    ], $versionActor, $versionReason);
                }
            }

            Cache::put(
                "studio_draft:{$article->id}",
                [
                    'content' => $article->content,
                    'word_count' => (int) $article->word_count,
                    'saved_at' => now()->toIso8601String(),
                ],
                now()->addDays(7)
            );

            return [
                'article_id' => $article->id,
                'word_count' => (int) $article->word_count,
                'saved_at' => now()->toIso8601String(),
                'version_number' => $version ? $version->version_number : $article->versionCount(),
                'has_version_created' => $version !== null,
                'status' => $article->status instanceof ArticleStatus ? $article->status->value : (string) $article->status,
            ];
        });
    }

    /**
     * Lấy lịch sử phiên bản của bài viết.
     */
    public function getVersions(Article $article, Workspace $workspace): Collection
    {
        $this->assertWorkspaceOwnership($article, $workspace);

        return $article->versions()
            ->with(['changedBy:id,name,email'])
            ->orderByDesc('version_number')
            ->get();
    }

    /**
     * Kiểm tra cách ly dữ liệu Multi-Tenant & Workspace.
     */
    protected function assertWorkspaceOwnership(Article $article, Workspace $workspace): void
    {
        if ((int) $article->workspace_id !== (int) $workspace->id) {
            throw new InvalidArgumentException('Bài viết không tồn tại hoặc không thuộc Workspace hiện tại.');
        }
    }

    /**
     * Fallback nội bộ khi chưa cấu hình AI Provider (dành cho môi trường offline).
     *
     * @param  array<string, mixed>  $options
     */
    protected function generateLocalFallback(string $action, string $text, array $options): string
    {
        return match ($action) {
            'expand' => $text.' Hơn thế nữa, việc áp dụng các tiêu chuẩn chất lượng cao sẽ giúp tối ưu hóa hiệu quả và mang lại giá trị bền vững lâu dài.',
            'shorten' => mb_substr($text, 0, (int) (mb_strlen($text) * 0.75)).'...',
            'change_tone' => sprintf('[Tone: %s] %s', $options['tone'] ?? 'professional', $text),
            'add_example' => $text.' Ví dụ thực tế: Một doanh nghiệp ứng dụng thành công đã tăng 45% lượng truy cập tự nhiên trong vòng 3 tháng.',
            'fix_grammar' => trim(preg_replace('/\s+/', ' ', $text) ?? $text),
            default => 'Được cải tiến: '.$text,
        };
    }
}
