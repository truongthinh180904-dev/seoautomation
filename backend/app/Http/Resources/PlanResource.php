<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code?->value ?? (string) $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'price_monthly_vnd' => (int) $this->price_monthly_vnd,
            'price_formatted' => number_format((int) $this->price_monthly_vnd).'đ',
            'billing_interval' => $this->billing_interval?->value ?? (string) $this->billing_interval,
            'credits_per_cycle' => (int) $this->credits_per_cycle,
            'articles_limit_per_cycle' => (int) $this->articles_limit_per_cycle,
            'websites_limit' => (int) $this->websites_limit,
            'max_concurrent_tasks' => (int) $this->max_concurrent_tasks,
            'max_retries_per_task' => (int) $this->max_retries_per_task,
            'max_images_per_article' => (int) $this->max_images_per_article,
            'carryover' => (bool) $this->carryover,
            'features' => $this->features ?? [],
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
