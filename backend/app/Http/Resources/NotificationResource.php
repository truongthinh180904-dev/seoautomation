<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type?->value,
            'channel' => $this->channel?->value,
            'subject' => $this->subject,
            'message' => $this->message,
            'data' => $this->data,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'read_at' => $this->read_at,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
