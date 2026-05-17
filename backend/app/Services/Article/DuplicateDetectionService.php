<?php

namespace App\Services\Article;

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
     * @throws \App\Exceptions\Article\DuplicateContentException
     */
    public function checkDuplicate(string $hash, ?int $excludeArticleId = null): void
    {
        $query = Article::where('duplicate_check_hash', $hash);

        if ($excludeArticleId) {
            $query->where('id', '!=', $excludeArticleId);
        }

        $existing = $query->first();

        if ($existing) {
            Log::warning("DuplicateDetectionService: Duplicate content detected. Hash: {$hash}, existing article #{$existing->id}");
            throw new \App\Exceptions\Article\DuplicateContentException(
                "Duplicate content detected. Matches article #{$existing->id}: \"{$existing->title}\""
            );
        }
    }

    /**
     * Compute hash and check at once. Returns the hash for saving.
     *
     * @throws \App\Exceptions\Article\DuplicateContentException
     */
    public function validateAndHash(string $htmlContent, ?int $excludeArticleId = null): string
    {
        $hash = $this->computeHash($htmlContent);
        $this->checkDuplicate($hash, $excludeArticleId);
        return $hash;
    }
}
