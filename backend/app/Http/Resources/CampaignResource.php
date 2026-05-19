<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'language' => $this->language,
            'brand_voice' => $this->brand_voice,
            'target_audience' => $this->target_audience,
            'content_goal' => $this->content_goal,
            'default_word_count' => $this->default_word_count,
            'default_category_ids' => $this->default_category_ids,
            'default_tag_names' => $this->default_tag_names,
            'approval_required' => $this->approval_required,
            'status' => $this->status?->value,
            'settings' => $this->settings,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'keywords_count' => $this->whenCounted('keywords'),
            'articles_count' => $this->whenCounted('articles'),
            'wordpress_site' => $this->whenLoaded('wordpressSite', function () {
                return [
                    'id' => $this->wordpressSite->id,
                    'name' => $this->wordpressSite->name,
                    'url' => $this->wordpressSite->url ?? null,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
