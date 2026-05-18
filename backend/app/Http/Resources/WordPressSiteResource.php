<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WordPressSiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'api_url' => $this->api_url,
            'username' => $this->username,
            'default_author_id' => $this->default_author_id,
            'default_category_id' => $this->default_category_id,
            'default_status' => $this->default_status,
            'connection_status' => $this->connection_status,
            'last_connected_at' => $this->last_connected_at,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
