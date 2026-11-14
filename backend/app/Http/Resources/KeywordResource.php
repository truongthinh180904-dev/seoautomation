<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KeywordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'keyword' => $this->keyword,
            'language' => $this->language,
            'search_volume' => $this->search_volume,
            'difficulty' => $this->difficulty,
            'cpc' => $this->cpc,
            'search_intent' => $this->search_intent,
            'priority' => $this->priority,
            'status' => $this->status?->value,
            'scheduled_at' => $this->scheduled_at,
            'processed_at' => $this->processed_at,
            'campaign' => $this->whenLoaded('campaign', function () {
                return $this->campaign ? [
                    'id' => $this->campaign->id,
                    'name' => $this->campaign->name,
                    'status' => $this->campaign->status?->value,
                ] : null;
            }),
            'pillar_topic' => $this->pillar_topic,
            'content_cluster' => $this->content_cluster,
            'funnel_stage' => $this->funnel_stage,
            'target_word_count' => $this->target_word_count,
            'target_url' => $this->target_url,
            'canonical_url' => $this->canonical_url,
            'brief_notes' => $this->brief_notes,
            'must_include_points' => $this->must_include_points,
            'avoid_topics' => $this->avoid_topics,
            'reference_urls' => $this->reference_urls,
            'competitor_urls_override' => $this->competitor_urls_override,
            'raw_import_row' => $this->raw_import_row,
            'template_version' => $this->template_version,
            'meta' => $this->meta,
            'batch_id' => $this->batch_id,
            'wordpress_site' => $this->whenLoaded('wordpressSite', function () {
                return [
                    'id' => $this->wordpressSite->id,
                    'name' => $this->wordpressSite->name,
                ];
            }),
            'article_id' => $this->whenLoaded('article', fn () => $this->article?->id),
            'article' => $this->whenLoaded('article', fn () => $this->article ? [
                'id' => $this->article->id,
                'status' => $this->article->status?->value,
                'review_notes' => $this->article->review_notes,
                'pipeline_status' => $this->article->pipeline_status,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
