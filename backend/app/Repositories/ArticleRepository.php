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
            ->where('tenant_id', $tenantId)
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
        $query = Article::where('tenant_id', $tenantId)
            ->with(['keyword:id,keyword', 'wordpressSite:id,name', 'user:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['keyword_id'])) {
            $query->where('keyword_id', $filters['keyword_id']);
        }

        if (!empty($filters['wordpress_site_id'])) {
            $query->where('wordpress_site_id', $filters['wordpress_site_id']);
        }

        if (!empty($filters['wp_site_id'])) {
            $query->where('wordpress_site_id', $filters['wp_site_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhereHas('keyword', fn ($keywordQuery) => $keywordQuery->where('keyword', 'like', '%' . $search . '%'));
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
        return Article::where('id', $id)->update($data) > 0;
    }

    public function updateStatus(int $id, ArticleStatus $status): bool
    {
        return Article::where('id', $id)->update(['status' => $status]) > 0;
    }

    public function deleteForTenant(int $id, int $tenantId): bool
    {
        return Article::where('tenant_id', $tenantId)
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
