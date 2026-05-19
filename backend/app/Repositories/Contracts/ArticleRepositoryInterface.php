<?php

namespace App\Repositories\Contracts;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Pagination\LengthAwarePaginator;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;
    public function findByIdForTenant(int $id, int $tenantId): ?Article;
    public function findByReviewToken(string $token): ?Article;
    public function findReviewableByToken(string $token): ?Article;
    public function findPendingReviewByToken(string $token): ?Article;
    public function paginateForTenant(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator;
    public function findByKeywordId(int $keywordId): ?Article;
    public function findByKeywordIdForTenant(int $keywordId, int $tenantId): ?Article;
    public function create(array $data): Article;
    public function update(int $id, array $data): bool;
    public function updateStatus(int $id, ArticleStatus $status): bool;
    public function deleteForTenant(int $id, int $tenantId): bool;
    public function checkDuplicate(string $contentHash, int $tenantId): bool;
    public function getStatsForTenant(int $tenantId): array;
}
