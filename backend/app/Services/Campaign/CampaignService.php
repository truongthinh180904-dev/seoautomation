<?php

namespace App\Services\Campaign;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(
        protected CampaignRepositoryInterface $campaigns
    ) {}

    public function create(array $data, int $tenantId, int $userId): Campaign
    {
        return $this->campaigns->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'created_by' => $userId,
            'slug' => $this->uniqueSlug($data['name'], $tenantId),
            'status' => $data['status'] ?? CampaignStatus::DRAFT,
        ]));
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if (isset($data['name']) && $data['name'] !== $campaign->name && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['name'], $campaign->tenant_id, $campaign->id);
        }

        $this->campaigns->update($campaign->id, $data);

        return $campaign->fresh(['wordpressSite:id,name,url', 'creator:id,name']);
    }

    public function setStatus(Campaign $campaign, CampaignStatus $status): Campaign
    {
        $data = ['status' => $status];

        if ($status === CampaignStatus::PROCESSING && !$campaign->started_at) {
            $data['started_at'] = now();
        }

        if ($status === CampaignStatus::COMPLETED) {
            $data['completed_at'] = now();
        }

        $this->campaigns->update($campaign->id, $data);

        return $campaign->fresh(['wordpressSite:id,name,url', 'creator:id,name']);
    }

    protected function uniqueSlug(string $name, int $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'campaign';
        $slug = $base;
        $counter = 2;

        while (Campaign::where('tenant_id', $tenantId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
