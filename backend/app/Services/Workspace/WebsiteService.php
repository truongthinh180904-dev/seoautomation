<?php

namespace App\Services\Workspace;

use App\Enums\WebsiteStatus;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Security\AuditLogService;
use Illuminate\Database\Eloquent\Collection;

class WebsiteService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function createWebsite(Workspace $workspace, array $data): Website
    {
        $domain = parse_url($data['url'], PHP_URL_HOST) ?: ($data['domain'] ?? $data['name']);

        $website = Website::create([
            'workspace_id' => $workspace->id,
            'wordpress_site_id' => $data['wordpress_site_id'] ?? null,
            'name' => $data['name'],
            'domain' => $domain,
            'url' => $data['url'],
            'default_language' => $data['default_language'] ?? 'vi',
            'niche' => $data['niche'] ?? null,
            'status' => $data['status'] ?? WebsiteStatus::ACTIVE,
            'settings' => $data['settings'] ?? [],
        ]);

        $this->auditLogService->log(
            action: 'website.created',
            auditable: $website,
            newValues: ['name' => $website->name, 'domain' => $website->domain, 'url' => $website->url],
            workspaceId: $workspace->id
        );

        return $website;
    }

    public function updateWebsite(Website $website, array $data): Website
    {
        $old = $website->only(['name', 'url', 'status', 'niche']);

        if (isset($data['url'])) {
            $data['domain'] = parse_url($data['url'], PHP_URL_HOST) ?: ($data['domain'] ?? $website->domain);
        }

        $website->update($data);

        $this->auditLogService->log(
            action: 'website.updated',
            auditable: $website,
            oldValues: $old,
            newValues: $website->only(['name', 'url', 'status', 'niche']),
            workspaceId: $website->workspace_id
        );

        return $website;
    }

    public function deleteWebsite(Website $website): bool
    {
        $workspaceId = $website->workspace_id;
        $deleted = $website->delete();

        if ($deleted) {
            $this->auditLogService->log(
                action: 'website.deleted',
                auditable: $website,
                oldValues: ['name' => $website->name, 'domain' => $website->domain],
                workspaceId: $workspaceId
            );
        }

        return (bool) $deleted;
    }

    public function getWebsitesForWorkspace(Workspace $workspace): Collection
    {
        return $workspace->websites()->with('wordpressSite')->latest()->get();
    }
}
