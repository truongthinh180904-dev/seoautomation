<?php

namespace App\DTOs\SEO;

use Spatie\LaravelData\Data;

class ContentDiffDTO extends Data
{
    /**
     * @param  array<string, mixed>  $criteriaBefore
     * @param  array<string, mixed>  $criteriaAfter
     * @param  array<int, string>  $addedHeadings
     * @param  array<int, string>  $missingIntentsAddressed
     * @param  array<int, array<string, mixed>>  $internalLinkSuggestions
     * @param  array<int, array{type: string, value: string}>  $diffSegments
     */
    public function __construct(
        public int $articleId,
        public ?int $insightId,
        public string $originalTitle,
        public string $proposedTitle,
        public ?string $originalSeoTitle,
        public ?string $proposedSeoTitle,
        public ?string $originalSeoDescription,
        public ?string $proposedSeoDescription,
        public ?string $focusKeyword,
        public string $originalContent,
        public string $proposedContent,
        public int $wordCountBefore,
        public int $wordCountAfter,
        public int $wordCountDelta,
        public int $scoreBefore,
        public int $scoreAfter,
        public int $scoreDelta,
        public array $criteriaBefore = [],
        public array $criteriaAfter = [],
        public array $addedHeadings = [],
        public array $missingIntentsAddressed = [],
        public array $internalLinkSuggestions = [],
        public array $diffSegments = [],
        public ?string $optimizationNotes = null,
        public ?string $createdAt = null,
    ) {}

    /**
     * Compute line-by-line diff segments between original and proposed content.
     *
     * @return array<int, array{type: string, value: string}>
     */
    public static function computeDiff(string $oldText, string $newText): array
    {
        $oldLines = preg_split('/\r\n|\r|\n/', $oldText) ?: [];
        $newLines = preg_split('/\r\n|\r|\n/', $newText) ?: [];

        $segments = [];
        $i = 0;
        $j = 0;
        $oldLen = count($oldLines);
        $newLen = count($newLines);

        while ($i < $oldLen || $j < $newLen) {
            if ($i < $oldLen && $j < $newLen && trim($oldLines[$i]) === trim($newLines[$j])) {
                $segments[] = [
                    'type' => 'unchanged',
                    'value' => $newLines[$j],
                ];
                $i++;
                $j++;
            } elseif ($j < $newLen && ($i >= $oldLen || ! in_array(trim($newLines[$j]), array_map('trim', array_slice($oldLines, $i, 6)), true))) {
                $segments[] = [
                    'type' => 'added',
                    'value' => $newLines[$j],
                ];
                $j++;
            } elseif ($i < $oldLen) {
                $segments[] = [
                    'type' => 'removed',
                    'value' => $oldLines[$i],
                ];
                $i++;
            } else {
                break;
            }
        }

        return $segments;
    }
}
