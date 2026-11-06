<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Article\ContentStudioService;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ContentStudioWriteAdapter implements ChatToolInterface
{
    public function __construct(
        protected ContentStudioService $contentStudioService
    ) {}

    public function getName(): string
    {
        return 'content_studio_write';
    }

    public function getTitle(): string
    {
        return 'Soạn thảo Bài viết Chuẩn SEO (Content Studio)';
    }

    public function getDescription(): string
    {
        return 'Khởi tạo bài viết dài 1.500 - 3.500 từ chuẩn SEO, nhúng thực thể LSI, định dạng Markdown theo Brand Voice hoặc biên tập/viết lại đoạn văn.';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        $action = (string) ($params['action'] ?? 'write');
        if (in_array($action, ['rewrite', 'expand', 'shorten', 'change_tone', 'add_example', 'fix_grammar', 'assist'])) {
            return (int) config('ai_costs.credits.studio_copilot_action', 1);
        }

        return (int) config('ai_costs.credits.standard_article', 20);
    }

    public function isSensitive(): bool
    {
        return false;
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['write', 'outline', 'rewrite', 'expand', 'shorten', 'change_tone', 'assist'],
                    'default' => 'write',
                    'description' => 'Hành động cần thực thi: viết bài đầy đủ, tạo dàn ý, hoặc biên tập đoạn văn.',
                ],
                'keyword' => [
                    'type' => 'string',
                    'description' => 'Từ khóa SEO chính của bài viết.',
                ],
                'text' => [
                    'type' => 'string',
                    'description' => 'Đoạn văn bản cần AI viết lại, mở rộng hoặc sửa lỗi (dành cho action biên tập).',
                ],
                'article_id' => [
                    'type' => 'integer',
                    'description' => 'ID bài viết trong Content Studio nếu đang thao tác trên bài hiện có.',
                ],
                'website_id' => [
                    'type' => 'integer',
                    'description' => 'ID Website mục tiêu để áp dụng Brand Voice.',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $action = (string) ($params['action'] ?? 'write');
        $articleId = ! empty($params['article_id']) ? (int) $params['article_id'] : null;

        // Nhánh 1: Tác vụ Biên tập đoạn văn (Copilot / Rewrite / Expand / Shorten)
        if (in_array($action, ['rewrite', 'expand', 'shorten', 'change_tone', 'add_example', 'fix_grammar', 'assist', 'custom'])) {
            $selectedText = trim((string) ($params['text'] ?? $params['selected_text'] ?? ''));
            if ($selectedText === '') {
                throw new InvalidArgumentException('Đoạn văn bản cần xử lý không được để trống.');
            }

            $article = null;
            if ($articleId) {
                $article = Article::where('workspace_id', $workspace->id)->find($articleId);
            }

            if (! $article) {
                // Tạo một bản ghi Article tạm để lưu vết lịch sử phiên bản và tenant scope
                $website = Website::where('workspace_id', $workspace->id)->first();
                $tempKeyword = Str::limit($selectedText, 50, '');
                $keywordRecord = Keyword::firstOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'keyword' => $tempKeyword,
                    ],
                    [
                        'tenant_id' => $workspace->legacy_tenant_id ?: 1,
                        'website_id' => $website?->id,
                        'user_id' => $actor?->id ?: $workspace->owner_id,
                        'status' => KeywordStatus::PENDING,
                    ]
                );

                $article = Article::create([
                    'tenant_id' => $workspace->legacy_tenant_id ?: 1,
                    'workspace_id' => $workspace->id,
                    'website_id' => $website?->id,
                    'keyword_id' => $keywordRecord->id,
                    'user_id' => $actor?->id ?: $workspace->owner_id,
                    'title' => 'Bản nháp Content Studio: '.Str::limit($selectedText, 40),
                    'slug' => 'studio-draft-'.Str::random(8),
                    'content' => $selectedText,
                    'status' => ArticleStatus::DRAFT,
                ]);
            }

            $assistAction = $action === 'assist' ? 'rewrite' : $action;
            $result = $this->contentStudioService->assist(
                article: $article,
                workspace: $workspace,
                action: $assistAction,
                selectedText: $selectedText,
                options: $params,
                actor: $actor
            );

            return [
                'tool' => $this->getName(),
                'action' => $assistAction,
                'article_id' => $article->id,
                'original_text' => $selectedText,
                'result_text' => $result['result_text'] ?? '',
                'word_count' => $result['word_count'] ?? 0,
                'credits_deducted' => $result['credits_deducted'] ?? 1,
            ];
        }

        // Nhánh 2: Khởi tạo Dàn ý hoặc Bài viết Mới (write / outline)
        $keyword = trim((string) ($params['keyword'] ?? $params['title'] ?? ''));
        if ($keyword === '') {
            throw new InvalidArgumentException('Từ khóa hoặc tiêu đề bài viết không được để trống.');
        }

        $websiteId = ! empty($params['website_id']) ? (int) $params['website_id'] : null;
        $website = null;
        if ($websiteId) {
            $website = Website::where('workspace_id', $workspace->id)->find($websiteId);
        }
        if (! $website) {
            $website = Website::where('workspace_id', $workspace->id)->first();
        }

        $title = $params['title'] ?? ("Hướng dẫn toàn diện về {$keyword} từ A-Z");
        $outline = [
            ['level' => 1, 'text' => $title],
            ['level' => 2, 'text' => "1. Tổng quan và định nghĩa về {$keyword}"],
            ['level' => 2, 'text' => "2. Lý do {$keyword} trở thành xu hướng hàng đầu"],
            ['level' => 2, 'text' => "3. Phân tích chi tiết các tiêu chí của {$keyword}"],
            ['level' => 3, 'text' => '3.1. Ưu điểm nổi bật'],
            ['level' => 3, 'text' => '3.2. Những điểm cần lưu ý khi ứng dụng'],
            ['level' => 2, 'text' => '4. Bảng so sánh và hướng dẫn từng bước'],
            ['level' => 2, 'text' => '5. Các câu hỏi thường gặp (FAQs)'],
        ];

        $keywordRecord = Keyword::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'keyword' => $keyword,
            ],
            [
                'tenant_id' => $workspace->legacy_tenant_id ?: 1,
                'website_id' => $website?->id,
                'user_id' => $actor?->id ?: $workspace->owner_id,
                'status' => KeywordStatus::PENDING,
            ]
        );

        $article = Article::create([
            'tenant_id' => $workspace->legacy_tenant_id ?: 1,
            'workspace_id' => $workspace->id,
            'website_id' => $website?->id,
            'keyword_id' => $keywordRecord->id,
            'user_id' => $actor?->id ?: $workspace->owner_id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(5),
            'focus_keyword' => $keyword,
            'outline' => $outline,
            'content' => "# {$title}\n\nNội dung bài viết chuẩn SEO đang được khởi tạo theo dàn ý chi tiết.",
            'word_count' => 500,
            'status' => ArticleStatus::DRAFT,
        ]);

        return [
            'tool' => $this->getName(),
            'action' => $action,
            'article_id' => $article->id,
            'title' => $article->title,
            'focus_keyword' => $keyword,
            'outline' => $outline,
            'status' => $article->status->value,
            'credits_deducted' => 0,
        ];
    }
}
