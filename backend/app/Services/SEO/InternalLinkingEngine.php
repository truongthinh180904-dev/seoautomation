<?php

namespace App\Services\SEO;

use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Services\Billing\CreditLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InternalLinkingEngine
{
    public function __construct(
        protected ?CreditLedgerService $creditLedger = null,
    ) {
        $this->creditLedger = $this->creditLedger ?? app(CreditLedgerService::class);
    }

    /**
     * Scan an article for internal linking opportunities towards other articles in the same website.
     *
     * @return array<int, InternalLinkSuggestion>
     */
    public function scanArticle(Article $sourceArticle): array
    {
        $targetArticles = Article::query()
            ->where('website_id', $sourceArticle->website_id)
            ->where('id', '!=', $sourceArticle->id)
            ->get();

        $suggestions = [];
        $content = (string) ($sourceArticle->content ?? '');
        $plainText = strip_tags($content);

        foreach ($targetArticles as $target) {
            $keyword = trim((string) ($target->focus_keyword ?: $target->title));
            if ($keyword === '') {
                continue;
            }

            $alreadyLinked = InternalLinkSuggestion::query()
                ->where('source_article_id', $sourceArticle->id)
                ->where('target_article_id', $target->id)
                ->where('status', 'applied')
                ->exists();

            if ($alreadyLinked) {
                continue;
            }

            $hasDirectMatch = mb_stripos($plainText, $keyword) !== false;
            $relevanceScore = 60.0;
            $contextSentence = null;

            if ($hasDirectMatch) {
                $relevanceScore = 92.5;
                $contextSentence = $this->extractContextSentence($plainText, $keyword);
            } else {
                // Semantic / topic similarity check
                $sharedWords = $this->countSharedWords($sourceArticle->title.' '.$sourceArticle->focus_keyword, $target->title.' '.$keyword);
                if ($sharedWords > 0) {
                    $relevanceScore = min(88.0, 60.0 + ($sharedWords * 10.0));
                }
                $contextSentence = sprintf('Để hiểu rõ hơn về chủ đề này, bạn có thể tham khảo thêm bài viết về %s.', $keyword);
            }

            $suggestion = InternalLinkSuggestion::firstOrCreate(
                [
                    'workspace_id' => $sourceArticle->workspace_id,
                    'website_id' => $sourceArticle->website_id,
                    'source_article_id' => $sourceArticle->id,
                    'target_article_id' => $target->id,
                    'suggested_anchor_text' => $keyword,
                ],
                [
                    'context_sentence' => $contextSentence,
                    'relevance_score' => $relevanceScore,
                    'status' => 'pending',
                ]
            );

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * Scan all articles in a website and optionally charge credits.
     *
     * @return array{total_articles: int, suggestions_found: int, suggestions: array<int, InternalLinkSuggestion>}
     */
    public function scanWebsite(int $websiteId, ?int $workspaceId = null): array
    {
        $articles = Article::query()->where('website_id', $websiteId)->get();

        if ($workspaceId && $this->creditLedger) {
            $scanCost = (int) config('ai_costs.credits.internal_link_scan', 1);
            if ($scanCost > 0) {
                $this->creditLedger->checkCredits($workspaceId, $scanCost);
                $this->creditLedger->deductCredits(
                    workspace: $workspaceId,
                    amount: $scanCost,
                    referenceType: 'internal_link_scan',
                    referenceId: $websiteId,
                    idempotencyKey: sprintf('deduct:%d:link_scan:%d:%s', $workspaceId, $websiteId, now()->format('YmdH')),
                    metadata: ['website_id' => $websiteId, 'articles_count' => $articles->count()]
                );
            }
        }

        $allSuggestions = [];
        foreach ($articles as $article) {
            $articleSuggestions = $this->scanArticle($article);
            foreach ($articleSuggestions as $sugg) {
                $allSuggestions[] = $sugg;
            }
        }

        return [
            'total_articles' => $articles->count(),
            'suggestions_found' => count($allSuggestions),
            'suggestions' => $allSuggestions,
        ];
    }

    /**
     * Apply a link suggestion directly to the source article HTML.
     */
    public function applySuggestion(InternalLinkSuggestion $suggestion): bool
    {
        return DB::transaction(function () use ($suggestion) {
            $suggestion->loadMissing(['sourceArticle', 'targetArticle']);
            $source = $suggestion->sourceArticle;
            $target = $suggestion->targetArticle;

            if (! $source || ! $target) {
                return false;
            }

            $url = $target->wordpress_post_url ?: ($target->canonical_url ?: '/articles/'.($target->slug ?: $target->id));
            $anchor = $suggestion->suggested_anchor_text;
            $content = (string) ($source->content ?? '');

            $escapedAnchor = preg_quote($anchor, '/');
            // Regex to match anchor text outside HTML tags and not already inside <a> tags (Unicode-safe word boundaries)
            $pattern = '/(?!(?:[^<]+>|[^>]+<\/a>))(?<![\p{L}\p{N}])('.$escapedAnchor.')(?![\p{L}\p{N}])/iu';

            $linkTag = sprintf('<a href="%s" title="%s">%s</a>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8'), $anchor);

            if (preg_match($pattern, $content)) {
                $newContent = (string) preg_replace($pattern, $linkTag, $content, 1);
            } else {
                // If not found in raw text, append context sentence
                $sentence = $suggestion->context_sentence ?: sprintf('Tham khảo thêm bài viết về %s.', $anchor);
                $sentenceWithLink = str_replace($anchor, $linkTag, $sentence);
                if (! str_contains($sentenceWithLink, '<a href=')) {
                    $sentenceWithLink .= ' '.$linkTag;
                }
                $newContent = $content."\n\n<p>".$sentenceWithLink.'</p>';
            }

            $source->content = $newContent;

            $currentLinks = is_array($source->internal_links) ? $source->internal_links : [];
            $currentLinks[] = [
                'suggestion_id' => $suggestion->id,
                'target_article_id' => $target->id,
                'target_url' => $url,
                'anchor_text' => $anchor,
                'applied_at' => now()->toIso8601String(),
            ];
            $source->internal_links = $currentLinks;
            $source->save();

            $suggestion->status = 'applied';
            $suggestion->save();

            return true;
        });
    }

    /**
     * Get nodes and edges for the website internal linking graph.
     *
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function getLinkGraph(int $websiteId): array
    {
        $articles = Article::query()
            ->where('website_id', $websiteId)
            ->select(['id', 'title', 'slug', 'status', 'focus_keyword'])
            ->get();

        $suggestions = InternalLinkSuggestion::query()
            ->where('website_id', $websiteId)
            ->get();

        $inboundCounts = [];
        $outboundCounts = [];

        foreach ($articles as $art) {
            $inboundCounts[$art->id] = 0;
            $outboundCounts[$art->id] = 0;
        }

        $edges = [];
        foreach ($suggestions as $sugg) {
            if (isset($outboundCounts[$sugg->source_article_id])) {
                $outboundCounts[$sugg->source_article_id]++;
            }
            if (isset($inboundCounts[$sugg->target_article_id])) {
                $inboundCounts[$sugg->target_article_id]++;
            }

            $edges[] = [
                'id' => $sugg->id,
                'source' => $sugg->source_article_id,
                'target' => $sugg->target_article_id,
                'anchor_text' => $sugg->suggested_anchor_text,
                'relevance_score' => $sugg->relevance_score,
                'status' => $sugg->status,
            ];
        }

        $nodes = [];
        foreach ($articles as $art) {
            $nodes[] = [
                'id' => $art->id,
                'title' => $art->title,
                'slug' => $art->slug,
                'status' => $art->status,
                'focus_keyword' => $art->focus_keyword,
                'inbound_count' => $inboundCounts[$art->id] ?? 0,
                'outbound_count' => $outboundCounts[$art->id] ?? 0,
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Get articles with zero applied inbound links.
     *
     * @return array<int, Article>
     */
    public function getOrphanArticles(int $websiteId): array
    {
        $articles = Article::query()
            ->where('website_id', $websiteId)
            ->get();

        $appliedTargetIds = InternalLinkSuggestion::query()
            ->where('website_id', $websiteId)
            ->where('status', 'applied')
            ->pluck('target_article_id')
            ->all();

        $orphans = [];
        foreach ($articles as $art) {
            if (! in_array($art->id, $appliedTargetIds, true)) {
                $orphans[] = $art;
            }
        }

        return $orphans;
    }

    /**
     * Extract a concise sentence containing the keyword from plain text.
     */
    protected function extractContextSentence(string $plainText, string $keyword): string
    {
        $escaped = preg_quote($keyword, '/');
        if (preg_match('/([^.?!;<\r\n]*(?:'.$escaped.')[^.?!;<\r\n]*[.?!]?)/iu', $plainText, $match)) {
            $sentence = trim($match[1]);
            if (mb_strlen($sentence) > 10) {
                return Str::limit($sentence, 200);
            }
        }

        return sprintf('Tìm hiểu thêm thông tin về %s trong bài viết hữu ích này.', $keyword);
    }

    /**
     * Count shared meaningful words between two strings.
     */
    protected function countSharedWords(string $strA, string $strB): int
    {
        $tokenize = function (string $str): array {
            $words = preg_split('/[\s,\.\-_:\/]+/u', mb_strtolower($str)) ?: [];

            return array_filter($words, fn (string $w) => mb_strlen($w) >= 3);
        };

        $wordsA = array_unique($tokenize($strA));
        $wordsB = array_unique($tokenize($strB));

        return count(array_intersect($wordsA, $wordsB));
    }
}
