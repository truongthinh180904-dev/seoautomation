<?php

namespace App\Services\Keyword;

use App\Models\Keyword;
use App\Services\Cost\CostTrackingService;

class KeywordImportPreviewService
{
    public function __construct(
        protected CostTrackingService $costTracking
    ) {}

    public function preview(array $rows, int $tenantId, ?int $campaignId = null): array
    {
        $errors = [];
        $warnings = [];
        $validRows = [];
        $seen = [];
        $existing = Keyword::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('keyword', array_column($rows, 'keyword'))
            ->pluck('keyword')
            ->map(fn (string $keyword) => mb_strtolower($keyword))
            ->flip()
            ->all();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $keyword = trim((string) ($row['keyword'] ?? ''));
            $rowErrors = [];

            if (mb_strlen($keyword) < 2) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'column' => 'keyword',
                    'message' => 'Keyword phải có ít nhất 2 ký tự.',
                ];
            }

            if (!empty($row['target_word_count']) && ((int) $row['target_word_count'] < 300 || (int) $row['target_word_count'] > 10000)) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'column' => 'target_word_count',
                    'message' => 'target_word_count phải từ 300 đến 10000.',
                ];
            }

            foreach (['target_url', 'canonical_url'] as $urlColumn) {
                if (!empty($row[$urlColumn]) && !filter_var($row[$urlColumn], FILTER_VALIDATE_URL)) {
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'column' => $urlColumn,
                        'message' => "{$urlColumn} không hợp lệ.",
                    ];
                }
            }

            $keywordKey = mb_strtolower($keyword);
            if (isset($seen[$keywordKey])) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'message' => "Keyword trùng với row {$seen[$keywordKey]}: '{$keyword}'.",
                ];
            }

            if (isset($existing[$keywordKey])) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'message' => "Keyword đã tồn tại trong hệ thống: '{$keyword}'.",
                ];
            }

            $seen[$keywordKey] = $rowNumber;

            if (!empty($rowErrors)) {
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            if (!isset($existing[$keywordKey]) && !isset($validRows[$keywordKey])) {
                $row['campaign_id'] = $row['campaign_id'] ?? $campaignId;
                $validRows[$keywordKey] = $row;
            }
        }

        $validCount = count($validRows);
        $estimatedPromptTokens = $validCount * 12000;
        $estimatedCompletionTokens = $validCount * 8000;
        $geminiCost = $this->costTracking->calculateCost(
            'gemini',
            config('ai.providers.gemini.default_model', 'gemini-2.5-flash'),
            $estimatedPromptTokens,
            $estimatedCompletionTokens
        );
        $serperCost = $validCount * config('ai_costs.serper_cost_per_call_usd', 0.001);
        $quota = $this->costTracking->checkQuota($tenantId)->toArray();

        return [
            'template_version' => 'v2',
            'total_rows' => count($rows),
            'valid_rows' => $validCount,
            'invalid_rows' => count($rows) - $validCount,
            'sample_rows' => array_slice(array_values($validRows), 0, 10),
            'errors' => $errors,
            'warnings' => $warnings,
            'estimated_cost' => [
                'serper_calls' => $validCount,
                'serper_cost_usd' => round($serperCost, 6),
                'gemini_tokens_estimated' => $estimatedPromptTokens + $estimatedCompletionTokens,
                'gemini_cost_estimated_usd' => round($geminiCost, 6),
                'total_estimated_usd' => round($serperCost + $geminiCost, 6),
            ],
            'quota_check' => [
                'articles_remaining' => $quota['articlesLimit'] === null
                    ? null
                    : max(0, $quota['articlesLimit'] - $quota['articlesUsed']),
                'budget_remaining_usd' => $quota['costLimitUsd'] === null
                    ? null
                    : max(0, round($quota['costLimitUsd'] - $quota['costUsedUsd'], 6)),
                'can_proceed' => $quota['canProceed'],
                'warning' => $quota['warningThreshold'] ? 'Bạn đã dùng hơn 80% quota tháng này.' : null,
            ],
        ];
    }
}
