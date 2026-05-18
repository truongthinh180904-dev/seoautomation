<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'keyword_id' => $this->keyword_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'focus_keyword' => $this->focus_keyword,
            'word_count' => $this->word_count,
            'seo_score' => $this->seo_score,
            'readability_score' => $this->readability_score,
            'outline' => $this->outline,
            'faqs' => $this->faqs,
            'internal_links' => $this->internal_links,
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'ai_tokens_used' => $this->ai_tokens_used,
            'ai_cost_usd' => $this->ai_cost_usd,
            'status' => $this->status?->value,
            'review_token' => $this->review_token,
            'review_notes' => $this->review_notes,
            'rejection_reason' => $this->rejection_reason,
            'scheduled_publish_at' => $this->scheduled_publish_at,
            'published_at' => $this->published_at,
            'wordpress_post_id' => $this->wordpress_post_id,
            'wordpress_post_url' => $this->wordpress_post_url,
            'keyword' => $this->whenLoaded('keyword', fn () => [
                'id' => $this->keyword->id,
                'keyword' => $this->keyword->keyword,
            ]),
            'wordpress_site' => $this->whenLoaded('wordpressSite', fn () => $this->wordpressSite ? [
                'id' => $this->wordpressSite->id,
                'name' => $this->wordpressSite->name,
                'url' => $this->wordpressSite->url,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
