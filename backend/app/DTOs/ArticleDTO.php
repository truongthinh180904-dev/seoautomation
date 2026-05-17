<?php

namespace App\DTOs;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Spatie\LaravelData\Data;

readonly class ArticleDTO extends Data
{
    public function __construct(
        public int $tenantId,
        public int $keywordId,
        public string $title,
        public ArticleStatus $status,
        public ?int $id = null,
        public ?string $content = null,
        public ?string $seoTitle = null,
        public ?string $seoDescription = null,
        public ?string $focusKeyword = null,
        public int $wordCount = 0,
        public ?int $seoScore = null,
        public ?array $outline = null,
        public ?array $faqs = null,
        public ?int $wordpressSiteId = null,
        public ?string $aiProvider = null,
        public ?string $aiModel = null,
        public int $aiTokensUsed = 0,
        public float $aiCostUsd = 0.0,
    ) {}

    public static function fromModel(Article $article): self
    {
        return new self(
            tenantId: $article->tenant_id,
            keywordId: $article->keyword_id,
            title: $article->title,
            status: $article->status instanceof ArticleStatus ? $article->status : ArticleStatus::from($article->status),
            id: $article->id,
            content: $article->content,
            seoTitle: $article->seo_title,
            seoDescription: $article->seo_description,
            focusKeyword: $article->focus_keyword,
            wordCount: $article->word_count ?? 0,
            seoScore: $article->seo_score,
            outline: is_string($article->outline) ? json_decode($article->outline, true) : $article->outline,
            faqs: is_string($article->faqs) ? json_decode($article->faqs, true) : $article->faqs,
            wordpressSiteId: $article->wordpress_site_id,
            aiProvider: $article->ai_provider,
            aiModel: $article->ai_model,
            aiTokensUsed: $article->ai_tokens_used ?? 0,
            aiCostUsd: (float) ($article->ai_cost_usd ?? 0.0),
        );
    }
}
