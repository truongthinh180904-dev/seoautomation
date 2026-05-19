<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'article_id' => $this->article_id,
            'source_type' => $this->source_type?->value,
            'source_url' => $this->source_url,
            'local_path' => $this->local_path,
            'wordpress_media_id' => $this->wordpress_media_id,
            'wordpress_media_url' => $this->wordpress_media_url,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'description' => $this->description,
            'credit' => $this->credit,
            'status' => $this->status?->value,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'article' => $this->whenLoaded('article', fn () => $this->article ? [
                'id' => $this->article->id,
                'title' => $this->article->title,
            ] : null),
            'campaign' => $this->whenLoaded('campaign', fn () => $this->campaign ? [
                'id' => $this->campaign->id,
                'name' => $this->campaign->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
