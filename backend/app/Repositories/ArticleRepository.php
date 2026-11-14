<?php

namespace App\Repositories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ArticleRepository implements ArticleRepositoryInterface
{
    public function findById(int $id): ?Article
    {
        return Article::find($id);
    }

    public function findByIdForTenant(int $id, int $tenantId): ?Article
    {
        return Article::with(['keyword', 'wordpressSite', 'user'])
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
                if (app()->bound('current_workspace_id')) {
                    $q->orWhere('workspace_id', app('current_workspace_id'));
                }
            })
            ->find($id);
    }

    public function findByReviewToken(string $token): ?Article
    {
        return Article::where('review_token', $token)
            ->with(['keyword', 'wordpressSite'])
            ->first();
    }

    public function findReviewableByToken(string $token): ?Article
    {
        return Article::with(['keyword:id,keyword', 'wordpressSite:id,name,url'])
            ->where('review_token', $token)
            ->whereIn('status', [ArticleStatus::REVIEW, ArticleStatus::APPROVED, ArticleStatus::REJECTED])
            ->first();
    }

    public function findPendingReviewByToken(string $token): ?Article
    {
        return Article::with(['keyword:id,keyword', 'wordpressSite:id,name,url'])
            ->where('review_token', $token)
            ->where('status', ArticleStatus::REVIEW)
            ->first();
    }

    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Article::query()
            ->with(['keyword:id,keyword', 'campaign:id,name,status', 'wordpressSite:id,name', 'user:id,name']);

        if (app()->bound('current_workspace_id')) {
            $workspaceId = app('current_workspace_id');
            $query->where(function ($q) use ($tenantId, $workspaceId) {
                $q->where('workspace_id', $workspaceId)
                    ->orWhere(function ($sub) use ($tenantId) {
                        $sub->whereNull('workspace_id')->where('tenant_id', $tenantId);
                    });
            });
        } else {
            $query->where('tenant_id', $tenantId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['keyword_id'])) {
            $query->where('keyword_id', $filters['keyword_id']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['wordpress_site_id'])) {
            $query->where('wordpress_site_id', $filters['wordpress_site_id']);
        }

        if (! empty($filters['wp_site_id'])) {
            $query->where('wordpress_site_id', $filters['wp_site_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('keyword', fn ($keywordQuery) => $keywordQuery->where('keyword', 'like', '%'.$search.'%'));
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function findByKeywordId(int $keywordId): ?Article
    {
        return Article::where('keyword_id', $keywordId)->first();
    }

    public function findByKeywordIdForTenant(int $keywordId, int $tenantId): ?Article
    {
        return Article::where('tenant_id', $tenantId)
            ->where('keyword_id', $keywordId)
            ->first();
    }

    public function create(array $data): Article
    {
        return Article::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $article = Article::find($id);
        if (! $article) {
            return false;
        }

        return $article->update($data);
    }

    public function updateStatus(int $id, ArticleStatus $status): bool
    {
        $article = Article::find($id);
        if (! $article) {
            return false;
        }

        return $article->update(['status' => $status]);
    }

    public function deleteForTenant(int $id, int $tenantId): bool
    {
        return Article::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
            if (app()->bound('current_workspace_id')) {
                $q->orWhere('workspace_id', app('current_workspace_id'));
            }
        })
            ->where('id', $id)
            ->delete() > 0;
    }

    public function checkDuplicate(string $contentHash, int $tenantId): bool
    {
        return Article::where('tenant_id', $tenantId)
            ->where('duplicate_check_hash', $contentHash)
            ->exists();
    }

    public function getStatsForTenant(int $tenantId): array
    {
        $counts = Article::where('tenant_id', $tenantId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $result = [];
        foreach ($counts as $count) {
            $result[$count->status->value] = $count->total;
        }

        return $result;
    }
}
