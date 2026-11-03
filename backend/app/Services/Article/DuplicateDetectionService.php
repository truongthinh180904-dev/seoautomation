<?php

namespace App\Services\Article;

use App\Exceptions\Article\DuplicateContentException;
use App\Models\Article;
use Illuminate\Support\Facades\Log;

class DuplicateDetectionService
{
    /**
     * Compute a canonical hash of HTML content (strips tags, lowercases, collapses whitespace).
     */
    public function computeHash(string $htmlContent): string
    {
        $text = strip_tags($htmlContent);
        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        return hash('sha256', $text);
    }

    /**
     * Check if a hash already exists in the articles table.
     *
     * @throws DuplicateContentException
     */
    public function checkDuplicate(
        string $hash,
        ?int $excludeArticleId = null,
        ?int $tenantId = null,
        ?int $workspaceId = null
    ): void {
        $query = Article::where('duplicate_check_hash', $hash);

        if ($excludeArticleId) {
            $query->where('id', '!=', $excludeArticleId);
            if (! $tenantId && ! $workspaceId) {
                $ref = Article::find($excludeArticleId);
                $tenantId = $ref?->tenant_id;
                $workspaceId = $ref?->workspace_id;
            }
        }

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        } elseif ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $existing = $query->first();

        if ($existing) {
            Log::warning("DuplicateDetectionService: Duplicate content detected. Hash: {$hash}, existing article #{$existing->id}");
            throw new DuplicateContentException(
                "Duplicate content detected. Matches existing article #{$existing->id} in your workspace."
            );
        }
    }

    /**
     * Compute hash and check at once. Returns the hash for saving.
     *
     * @throws DuplicateContentException
     */
    public function validateAndHash(
        string $htmlContent,
        ?int $excludeArticleId = null,
        ?int $tenantId = null,
        ?int $workspaceId = null
    ): string {
        $hash = $this->computeHash($htmlContent);
        $this->checkDuplicate($hash, $excludeArticleId, $tenantId, $workspaceId);

        return $hash;
    }
}
