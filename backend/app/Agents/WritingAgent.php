<?php

namespace App\Agents;

use App\DTOs\AgentResultDTO;
use App\DTOs\AIRequestDTO;
use App\Enums\AgentType;
use App\Services\AI\JsonResponseParser;
use App\Services\Article\ArticleContentFormatter;
use App\Services\Article\DuplicateDetectionService;
use Illuminate\Support\Facades\Log;

class WritingAgent extends BaseAgent
{
    public function getType(): AgentType
    {
        return AgentType::WRITING;
    }

    protected function run(array $context): AgentResultDTO
    {
        $keyword = $context['keyword'] ?? null;
        $outline = $context['outline'] ?? [];
        $tenantId = $context['tenant_id'] ?? null;
        $brandContext = (string) ($context['brand_context'] ?? '');
        $strategyBlock = (string) ($context['strategy_block'] ?? '');

        if (! $keyword || empty($outline) || ! $tenantId) {
            throw new \InvalidArgumentException('Missing required context fields.');
        }

        $semanticKeywords = implode(', ', $context['semantic_keywords'] ?? []);
        $entities = implode(', ', $context['entities'] ?? []);
        $targetWordCount = max(800, min(5000, (int) ($context['target_word_count'] ?? 1800)));
        $minWordCount = max(650, (int) round($targetWordCount * 0.75));
        $maxWordCount = (int) round($targetWordCount * 1.20);

        $faqsToAnswer = '';
        if (! empty($context['faqs']) && is_array($context['faqs'])) {
            $faqsToAnswer = json_encode($context['faqs'], JSON_UNESCAPED_UNICODE);
        }

        // Bỏ trường strategy_analysis quá lớn khỏi outline_json trước khi truyền vào prompt để tiết kiệm token
        $cleanOutlineForPrompt = is_array($outline) ? $outline : [];
        unset($cleanOutlineForPrompt['strategy_analysis']);

        $promptData = $this->promptBuilderService->build($this->getType(), [
            'keyword' => $keyword,
            'outline_json' => json_encode($cleanOutlineForPrompt, JSON_UNESCAPED_UNICODE),
            'semantic_keywords' => $semanticKeywords,
            'entities' => $entities,
            'faqs_to_answer' => $faqsToAnswer,
            'target_word_count' => $targetWordCount,
            'brief_notes' => $context['brief_notes'] ?? '',
            'must_include_points' => implode("\n", $context['must_include_points'] ?? []),
            'avoid_topics' => implode("\n", $context['avoid_topics'] ?? []),
        ], $tenantId);

        $systemPrompt = $promptData['system_prompt'];
        if ($brandContext !== '') {
            $systemPrompt .= "\n\n".$brandContext;
        }

        $universalRules = "\n\n=== 10 NGUYÊN TẮC VIẾT BÀI UNIVERSAL SEO CONTENT WRITER (BẮT BUỘC) ===\n"
            ."1. Viết tự nhiên như một chuyên gia am hiểu sâu chủ đề.\n"
            ."2. Không viết theo kiểu AI lặp lại các câu sáo rỗng.\n"
            ."3. Không mở đầu bằng những câu chung chung không mang giá trị (như 'Trong thời đại hiện nay...', 'Như chúng ta đã biết...').\n"
            ."4. Không kéo dài bài chỉ để đạt số lượng từ.\n"
            ."5. Không lặp từ khóa một cách máy móc.\n"
            ."6. Không tạo ra các heading chỉ để chèn từ khóa.\n"
            ."7. Không sử dụng thông tin không có căn cứ.\n"
            ."8. Không tự nhận mình là chuyên gia, bác sĩ, luật sư hoặc người có trải nghiệm thực tế.\n"
            ."9. Không tự sáng tạo số liệu, nghiên cứu, giấy phép, chứng nhận, đánh giá khách hàng (chỉ dùng dữ liệu đã xác minh).\n"
            ."10. Không đưa ra lời khuyên chuyên môn vượt quá dữ liệu và nguồn xác thực.\n"
            ."\nYêu cầu định dạng bắt buộc:\n"
            ."- Return ONLY valid JSON (gồm các khóa: title, content, excerpt), không bọc markdown/code fence.\n"
            ."- Độ dài mục tiêu: khoảng {$targetWordCount} từ (phạm vi {$minWordCount}–{$maxWordCount} từ); đi sâu vào chi tiết kỹ thuật/quy trình thực tế nhưng không viết lan man.\n"
            ."- content phải là HTML sạch cho WordPress, sử dụng <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <thead>, <tbody>, <tr>, <th>, <td>.\n"
            ."- Không dùng <h1> trong content vì title đã được lưu riêng.\n"
            ."- Không lặp lại title ở đầu content.\n"
            ."- Bố cục dễ đọc (scannable): đoạn văn ngắn 2–4 câu, sử dụng bullet points hoặc bảng so sánh (<table>) ở nơi giúp người đọc dễ đối chiếu.\n"
            ."- Chèn Internal Links tự nhiên với anchor text giàu ngữ nghĩa nếu có trong danh sách cơ hội liên kết.\n"
            ."- Nếu thuộc ngành YMYL (Y tế/Nha khoa, Pháp lý, Tài chính, Bất động sản), cuối bài phải có hộp lưu ý tham khảo chuyên môn (Disclaimer) phù hợp.";

        $userPrompt = $promptData['user_prompt'];
        if ($strategyBlock !== '') {
            $userPrompt .= "\n\n".$strategyBlock;
        }
        $userPrompt .= $universalRules;

        $preferredModel = (string) config('ai.providers.gemini.content_model', 'gemini-3.1-pro-preview');

        $request = new AIRequestDTO(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            model: $preferredModel,
            agentType: $this->getType(),
            tenantId: $tenantId,
            maxTokens: 16000,
            articleId: $context['article_id'] ?? null,
            keywordId: $context['keyword_id'] ?? null,
            promptVersion: $promptData['version'],
            workspaceId: $context['workspace_id'] ?? null,
        );

        $response = $this->callAI($request);
        $parser = app(JsonResponseParser::class);
        $parsed = $parser->parse($response->content);

        if (! $parsed) {
            Log::warning('WritingAgent JSON parse failed, falling back to raw content', [
                'keyword_id' => $context['keyword_id'] ?? null,
                'article_id' => $context['article_id'] ?? null,
                'model' => $response->model,
                'response_preview' => $parser->preview($response->content),
            ]);

            $parsed = $this->buildArticleFromRawResponse($response->content, $keyword);
        }

        $title = trim((string) ($parsed['title'] ?? ''));
        $htmlContent = app(ArticleContentFormatter::class)->normalize((string) ($parsed['content'] ?? ''), $title);
        $excerpt = trim((string) ($parsed['excerpt'] ?? ''));

        if (empty($title) || empty($htmlContent)) {
            return AgentResultDTO::failure($this->getType(), 'Article validation failed: Missing title or content');
        }

        if (mb_stripos($title, $keyword, 0, 'UTF-8') === false) {
            $title = "{$keyword}: {$title}";
        }

        $formatter = app(ArticleContentFormatter::class);
        $wordCount = $formatter->wordCount($htmlContent);
        if ($wordCount <= 500) {
            return AgentResultDTO::failure($this->getType(), "Article validation failed: Word count too low ({$wordCount})");
        }

        // Duplicate detection — compute hash and check against existing articles
        $detector = app(DuplicateDetectionService::class);
        $tenantId = $context['tenant_id'] ?? null;
        $workspaceId = $context['workspace_id'] ?? null;
        $contentHash = $detector->validateAndHash($htmlContent, $context['article_id'] ?? null, $tenantId, $workspaceId);

        return AgentResultDTO::success(
            agent: $this->getType(),
            data: [
                'title' => $title,
                'content' => $htmlContent,
                'excerpt' => $excerpt,
                'word_count' => $wordCount,
                'ai_provider' => $response->provider->value,
                'ai_model' => $response->model,
                'ai_cost_usd' => $response->totalCostUsd(),
                'duplicate_check_hash' => $contentHash,
            ],
            tokens: $response->totalTokens,
            latency: $response->latencyMs
        );
    }

    protected function buildArticleFromRawResponse(string $content, string $keyword): array
    {
        $content = app(JsonResponseParser::class)->clean($content);
        $content = trim($content);

        if (! str_contains($content, '<')) {
            $paragraphs = array_filter(array_map('trim', preg_split('/\R{2,}/', $content) ?: []));
            $content = '<h1>'.e($keyword).'</h1>'.implode('', array_map(
                fn (string $paragraph) => '<p>'.nl2br(e($paragraph)).'</p>',
                $paragraphs
            ));
        }

        $title = $this->extractTitle($content) ?: $keyword;
        $excerpt = mb_substr(trim(strip_tags($content)), 0, 220);

        return [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
        ];
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return null;
    }
}
