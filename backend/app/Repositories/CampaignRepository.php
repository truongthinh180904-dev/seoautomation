<?php

namespace App\Repositories;

use App\Models\Campaign;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Campaign::where('tenant_id', $tenantId)
            ->with(['wordpressSite:id,name', 'creator:id,name'])
            ->withCount(['keywords', 'articles']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['wordpress_site_id'])) {
            $query->where('wordpress_site_id', $filters['wordpress_site_id']);
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->latest()->paginate($perPage);
    }

    public function findByIdForTenant(int $id, int $tenantId): ?Campaign
    {
        return Campaign::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
            if (app()->bound('current_workspace_id')) {
                $q->orWhere('workspace_id', app('current_workspace_id'));
            }
        })
            ->with(['wordpressSite:id,name,url', 'creator:id,name'])
            ->withCount(['keywords', 'articles'])
            ->find($id);
    }

    public function create(array $data): Campaign
    {
        return Campaign::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $campaign = Campaign::find($id);
        if (! $campaign) {
            return false;
        }

        return $campaign->update($data);
    }

    public function deleteForTenant(int $id, int $tenantId): bool
    {
        return Campaign::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
            if (app()->bound('current_workspace_id')) {
                $q->orWhere('workspace_id', app('current_workspace_id'));
            }
        })
            ->where('id', $id)
            ->delete() > 0;
    }
}
