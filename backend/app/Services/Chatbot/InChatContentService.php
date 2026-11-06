<?php

namespace App\Services\Chatbot;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Article\ArticleContentFormatter;
use App\Services\Article\ContentStudioService;
use App\Services\Billing\CreditLedgerService;
use App\Services\SEO\SeoScoringEngine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class InChatContentService
{
    public function __construct(
        protected ContentStudioService $contentStudioService,
        protected CreditLedgerService $creditLedger,
        protected ?SeoScoringEngine $seoScoringEngine = null,
        protected ?ArticleContentFormatter $formatter = null
    ) {
        $this->seoScoringEngine = $this->seoScoringEngine ?? app(SeoScoringEngine::class);
        $this->formatter = $this->formatter ?? (app()->bound(ArticleContentFormatter::class) ? app(ArticleContentFormatter::class) : new ArticleContentFormatter);
    }

    /**
     * Thực hiện tác vụ AI Copilot trên một đoạn văn bản trong khung chat.
     * Trừ chính xác 1 credit qua CreditLedgerService (idempotency an toàn).
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

        // Đảm bảo idempotency key rõ ràng cho in-chat
        $idempotencyKey = $options['idempotency_key'] ?? sprintf(
            'in_chat_assist:%d:%d:%s',
            $workspace->id,
            $article->id,
            Str::uuid()->toString()
        );

        $options['idempotency_key'] = $idempotencyKey;

        // Kế thừa và tận dụng tối đa ContentStudioService sẵn có
        $result = $this->contentStudioService->assist(
            article: $article,
            workspace: $workspace,
            action: $action,
            selectedText: $selectedText,
            options: $options,
            actor: $actor
        );

        return array_merge($result, [
            'article_id' => $article->id,
            'source' => 'in_chat_copilot',
            'word_count_delta' => ($result['word_count'] ?? 0) - $this->formatter->wordCount($selectedText),
        ]);
    }

    /**
     * Thay thế một đoạn văn bản trong nội dung bài viết và tự động tính toán lại SEO score, word count.
     *
     * @param  array{create_version?: bool, version_reason?: ?string}  $options
     * @return array<string, mixed>
     */
    public function applySegmentEdit(
        Article $article,
        Workspace $workspace,
        string $originalText,
        string $replacementText,
        ?string $targetHeading = null,
        array $options = [],
        ?User $actor = null
    ): array {
        $this->assertWorkspaceOwnership($article, $workspace);

        $originalText = trim($originalText);
        $replacementText = trim($replacementText);

        if ($replacementText === '') {
            throw new InvalidArgumentException('Đoạn văn bản thay thế không được để trống.');
        }

        return DB::transaction(function () use ($article, $workspace, $originalText, $replacementText, $targetHeading, $options, $actor) {
            $currentContent = (string) ($article->content ?? '');
            $updatedContent = $currentContent;
            $replaced = false;

            // Cách 1: Thay thế trực tiếp chuỗi chính xác
            if ($originalText !== '' && str_contains($currentContent, $originalText)) {
                $pos = strpos($currentContent, $originalText);
                if ($pos !== false) {
                    $updatedContent = substr_replace($currentContent, $replacementText, $pos, strlen($originalText));
                    $replaced = true;
                }
            }

            // Cách 2: Nếu có targetHeading và chưa replace thành công, chèn hoặc thay thế dưới heading đó
            if (! $replaced && ! empty($targetHeading)) {
                $headingPattern = '/(<h[23][^>]*>'.preg_quote(strip_tags($targetHeading), '/').'<\/h[23]>)/i';
                if (preg_match($headingPattern, $currentContent, $matches, PREG_OFFSET_CAPTURE)) {
                    $matchPos = $matches[0][1];
                    $matchLen = strlen($matches[0][0]);
                    $insertPos = $matchPos + $matchLen;

                    $newSegment = "\n<p>{$replacementText}</p>";
                    $updatedContent = substr($currentContent, 0, $insertPos).$newSegment.substr($currentContent, $insertPos);
                    $replaced = true;
                }
            }

            // Cách 3: Nếu không tìm thấy vị trí cụ thể, nối vào cuối nội dung bài viết
            if (! $replaced) {
                $updatedContent = $currentContent."\n<p>{$replacementText}</p>";
                $replaced = true;
            }

            // Tự động tính toán lại các chỉ số
            $newWordCount = $this->formatter->wordCount($updatedContent);

            // Chấm điểm lại SEO tức thì (0 credit)
            $seoAudit = null;
            try {
                $seoAudit = $this->seoScoringEngine->evaluateRaw(
                    content: $updatedContent,
                    keyword: (string) ($article->focus_keyword ?: $article->title)
                );
            } catch (Throwable) {
                // Giữ nguyên điểm cũ nếu gặp lỗi
            }

            $newSeoScore = $seoAudit ? ($seoAudit['total_score'] ?? $seoAudit['score'] ?? $article->seo_score) : $article->seo_score;
            $newReadability = $seoAudit ? ($seoAudit['readability_score'] ?? $article->readability_score) : $article->readability_score;

            // Gọi autosave của ContentStudioService để đảm bảo nhất quán và lưu version
            $saveData = [
                'content' => $updatedContent,
                'create_version' => $options['create_version'] ?? true,
                'version_reason' => $options['version_reason'] ?? 'Biên tập trực tiếp từ Aura AI Chatbot',
            ];

            $this->contentStudioService->autosave($article, $workspace, $saveData, $actor);

            $article->seo_score = (int) $newSeoScore;
            $article->readability_score = (int) $newReadability;
            $article->save();

            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'previous_word_count' => (int) $article->word_count,
                'new_word_count' => $newWordCount,
                'seo_score' => (int) $article->seo_score,
                'readability_score' => (int) $article->readability_score,
                'status' => $article->status instanceof ArticleStatus ? $article->status->value : (string) $article->status,
                'replaced' => $replaced,
                'updated_at' => $article->updated_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Thêm một phân đoạn (section) mới gồm tiêu đề H2/H3 và nội dung vào bài viết.
     *
     * @return array<string, mixed>
     */
    public function insertSection(
        Article $article,
        Workspace $workspace,
        string $heading,
        string $content,
        string $level = 'h2',
        ?User $actor = null
    ): array {
        $this->assertWorkspaceOwnership($article, $workspace);

        $cleanHeading = trim(strip_tags($heading));
        $cleanContent = trim($content);
        $tag = in_array(strtolower($level), ['h2', 'h3'], true) ? strtolower($level) : 'h2';

        $sectionHtml = "\n<{$tag}>{$cleanHeading}</{$tag}>\n<p>{$cleanContent}</p>\n";

        return DB::transaction(function () use ($article, $workspace, $cleanHeading, $sectionHtml, $tag, $actor) {
            $updatedContent = (string) ($article->content ?? '').$sectionHtml;

            // Cập nhật outline cấu trúc bài viết
            $outline = is_array($article->outline) ? $article->outline : [];
            $outline[] = [
                'level' => $tag,
                'heading' => $cleanHeading,
                'word_count_target' => $this->formatter->wordCount($sectionHtml),
            ];

            $this->contentStudioService->autosave($article, $workspace, [
                'content' => $updatedContent,
                'outline' => $outline,
                'create_version' => true,
                'version_reason' => "Thêm mục {$tag}: {$cleanHeading} từ Chatbot",
            ], $actor);

            return [
                'article_id' => $article->id,
                'heading_added' => $cleanHeading,
                'level' => $tag,
                'word_count' => $this->formatter->wordCount($updatedContent),
                'total_headings' => count($outline),
            ];
        });
    }

    /**
     * Tải dữ liệu xem trước toàn diện của bài viết nháp cho thẻ InChatArticleCard.
     *
     * @return array<string, mixed>
     */
    public function getArticlePreview(Article $article, Workspace $workspace): array
    {
        $this->assertWorkspaceOwnership($article, $workspace);

        $article->loadMissing([
            'website:id,name,domain,url',
            'keyword:id,keyword',
            'mediaAssets',
        ]);

        $content = (string) ($article->content ?? '');
        $headings = $this->extractHeadingsWithContent($content);

        // Nhóm MediaAssets theo heading hoặc inline
        $mediaAssets = $article->mediaAssets->map(function ($asset) {
            $meta = is_array($asset->metadata) ? $asset->metadata : [];

            return [
                'id' => $asset->id,
                'url' => $asset->source_url ?: $asset->local_path,
                'alt_text' => $asset->alt_text,
                'caption' => $asset->caption,
                'heading' => $meta['heading'] ?? null,
                'source_type' => $asset->source_type instanceof \BackedEnum ? $asset->source_type->value : (string) $asset->source_type,
                'format' => $meta['format'] ?? 'webp',
                'size_bytes' => $meta['size_bytes'] ?? null,
                'is_under_150kb' => isset($meta['size_bytes']) ? ($meta['size_bytes'] <= 153600) : true,
                'provider' => $meta['provider'] ?? 'system',
            ];
        })->values()->all();

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'focus_keyword' => $article->focus_keyword,
            'excerpt' => $article->excerpt ?: Str::limit(strip_tags($content), 200),
            'word_count' => (int) ($article->word_count ?: $this->formatter->wordCount($content)),
            'seo_score' => (int) $article->seo_score,
            'readability_score' => (int) $article->readability_score,
            'status' => $article->status instanceof ArticleStatus ? $article->status->value : (string) $article->status,
            'website' => $article->website ? [
                'id' => $article->website->id,
                'name' => $article->website->name,
                'domain' => $article->website->domain,
            ] : null,
            'keyword' => $article->keyword ? [
                'id' => $article->keyword->id,
                'keyword' => $article->keyword->keyword,
            ] : null,
            'headings' => $headings,
            'media_assets' => $mediaAssets,
            'last_modified_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Bóc tách các thẻ H2 và H3 kèm nội dung tóm tắt tương ứng trong bài viết HTML.
     *
     * @return array<int, array{level: string, heading: string, preview_text: string, has_image: bool}>
     */
    public function extractHeadingsWithContent(string $html): array
    {
        $headings = [];
        preg_match_all('/(<h([23])[^>]*>(.*?)<\/h\2>)(.*?)(?=(<h[23]|$))/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $level = 'h'.$match[2];
            $headingTitle = trim(strip_tags($match[3]));
            $sectionBody = $match[4] ?? '';
            $previewText = Str::limit(trim(strip_tags($sectionBody)), 140);
            $hasImage = (bool) preg_match('/<img[^>]+>/i', $sectionBody);

            $headings[] = [
                'level' => $level,
                'heading' => $headingTitle,
                'preview_text' => $previewText,
                'has_image' => $hasImage,
            ];
        }

        // Nếu nội dung chưa có cấu trúc H2/H3 dạng đầy đủ, bóc tách danh sách đơn giản
        if (empty($headings)) {
            preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $h2Matches);
            foreach ($h2Matches[1] ?? [] as $title) {
                $headings[] = [
                    'level' => 'h2',
                    'heading' => trim(strip_tags($title)),
                    'preview_text' => '',
                    'has_image' => false,
                ];
            }
        }

        return $headings;
    }

    /**
     * Kiểm tra tính cô lập Multi-Tenant & Workspace.
     */
    protected function assertWorkspaceOwnership(Article $article, Workspace $workspace): void
    {
        if ((int) $article->workspace_id !== (int) $workspace->id) {
            throw new ModelNotFoundException('Bài viết không tồn tại hoặc không thuộc Không gian làm việc hiện tại.');
        }
    }
}
