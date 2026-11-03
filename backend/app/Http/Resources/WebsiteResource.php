<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'wordpress_site_id' => $this->wordpress_site_id,
            'name' => $this->name,
            'domain' => $this->domain,
            'url' => $this->url,
            'default_language' => $this->default_language,
            'niche' => $this->niche,
            'status' => $this->status?->value ?? (string) $this->status,
            'wordpress_site' => $this->whenLoaded('wordpressSite', function () {
                return [
                    'id' => $this->wordpressSite->id,
                    'name' => $this->wordpressSite->name,
                    'url' => $this->wordpressSite->url,
                    'is_active' => $this->wordpressSite->is_active,
                    'connection_status' => $this->wordpressSite->connection_status,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
