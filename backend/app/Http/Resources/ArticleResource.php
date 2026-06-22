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
            'campaign_id' => $this->campaign_id,
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
            'wp_post_type' => $this->wp_post_type,
            'wp_status' => $this->wp_status,
            'wp_category_ids' => $this->wp_category_ids,
            'wp_tag_ids' => $this->wp_tag_ids,
            'wp_tag_names' => $this->wp_tag_names,
            'wp_author_id' => $this->wp_author_id,
            'wp_slug' => $this->wp_slug,
            'canonical_url' => $this->canonical_url,
            'primary_cta' => $this->primary_cta,
            'secondary_cta' => $this->secondary_cta,
            'media_plan' => $this->media_plan,
            'image_assets' => $this->image_assets,
            'quality_report' => $this->quality_report,
            'pipeline_status' => $this->pipeline_status,
            'approval_required' => $this->approval_required,
            'approved_at' => $this->approved_at,
            'keyword' => $this->whenLoaded('keyword', fn () => [
                'id' => $this->keyword->id,
                'keyword' => $this->keyword->keyword,
                'search_intent' => $this->keyword->search_intent,
                'target_word_count' => $this->keyword->target_word_count,
                'target_url' => $this->keyword->target_url,
                'canonical_url' => $this->keyword->canonical_url,
                'brief_notes' => $this->keyword->brief_notes,
                'must_include_points' => $this->keyword->must_include_points,
                'avoid_topics' => $this->keyword->avoid_topics,
                'reference_urls' => $this->keyword->reference_urls,
                'competitor_urls_override' => $this->keyword->competitor_urls_override,
                'raw_import_row' => $this->keyword->raw_import_row,
                'meta' => $this->keyword->meta,
            ]),
            'campaign' => $this->whenLoaded('campaign', fn () => $this->campaign ? [
                'id' => $this->campaign->id,
                'name' => $this->campaign->name,
                'status' => $this->campaign->status?->value,
            ] : null),
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
