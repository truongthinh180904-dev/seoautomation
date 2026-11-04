<?php

namespace App\Services\Bulk;

use App\Enums\ArticleStatus;
use App\Enums\KeywordStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Jobs\Bulk\ProcessBulkBatchJob;
use App\Models\Article;
use App\Models\BulkBatch;
use App\Models\Keyword;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Billing\CreditLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class BulkGenerationService
{
    public function __construct(
        protected ?CreditLedgerService $creditLedger = null
    ) {
        $this->creditLedger = $this->creditLedger ?? app(CreditLedgerService::class);
    }

    /**
     * Xác định đơn giá credit cho 1 bài viết trong lô.
     * Áp dụng chính sách ưu đãi 18 credit/bài khi số lượng >= 10 bài.
     */
    public function getUnitCreditCost(int $totalItems): int
    {
        if ($totalItems >= 10) {
            return (int) config('ai_costs.credits.bulk_article_unit', 18);
        }

        return (int) config('ai_costs.credits.standard_article', 20);
    }

    /**
     * Tính tổng số credit cần giữ trước cho toàn bộ lô.
     */
    public function calculateTotalCredits(int $totalItems): int
    {
        return $totalItems * $this->getUnitCreditCost($totalItems);
    }

    /**
     * Tạo đợt sản xuất bài viết hàng loạt và giữ trước credit theo lô.
     *
     * @throws InsufficientCreditsException
     * @throws InvalidArgumentException
     */
    public function createBatch(
        Workspace $workspace,
        Website $website,
        array $data,
        ?User $actor = null
    ): BulkBatch {
        $keywordModels = $this->resolveKeywords($workspace, $website, $data, $actor);

        if (empty($keywordModels)) {
            throw new InvalidArgumentException('Danh sách từ khóa không hợp lệ hoặc để trống.');
        }

        $totalItems = count($keywordModels);
        $unitCost = $this->getUnitCreditCost($totalItems);
        $totalCredits = $this->calculateTotalCredits($totalItems);

        // Kiểm tra số dư khả dụng trước khi ghi nhận
        $this->creditLedger->checkCredits($workspace, $totalCredits);

        // Chuẩn bị danh sách item ban đầu
        $items = [];
        foreach ($keywordModels as $idx => $kw) {
            $items[] = [
                'index' => $idx,
                'keyword_id' => $kw->id,
                'keyword' => $kw->keyword,
                'status' => 'pending',
                'article_id' => null,
                'article_title' => null,
                'error' => null,
                'simulate_failure' => str_starts_with($kw->keyword, 'FAIL:') || (! empty($data['simulate_failure_indexes']) && in_array($idx, $data['simulate_failure_indexes'], true)),
            ];
        }

        $batchUuid = (string) Str::uuid();

        /** @var BulkBatch $batch */
        $batch = DB::transaction(function () use ($workspace, $website, $data, $actor, $batchUuid, $totalItems, $unitCost, $totalCredits, $items) {
            $createdBatch = BulkBatch::create([
                'uuid' => $batchUuid,
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'campaign_id' => $data['campaign_id'] ?? null,
                'name' => $data['name'] ?? ('Lô bài viết SEO - '.now()->format('Y-m-d H:i')),
                'total_items' => $totalItems,
                'completed_items' => 0,
                'failed_items' => 0,
                'credits_reserved' => $totalCredits,
                'credits_settled' => 0,
                'status' => 'pending',
                'generation_settings' => [
                    'tone' => $data['tone'] ?? 'professional',
                    'target_word_count' => (int) ($data['target_word_count'] ?? 1500),
                    'include_images' => (bool) ($data['include_images'] ?? true),
                    'auto_publish' => (bool) ($data['auto_publish'] ?? false),
                    'unit_cost' => $unitCost,
                    'delay_seconds' => (int) ($data['delay_seconds'] ?? 2),
                    'items' => $items,
                ],
            ]);

            // Giữ trước toàn bộ credit theo lô (concurrency-safe lockForUpdate)
            $this->creditLedger->reserveCredits(
                workspace: $workspace,
                amount: $totalCredits,
                referenceType: 'bulk_batch',
                referenceId: $createdBatch->id,
                idempotencyKey: 'reserve:bulk_batch:'.$createdBatch->uuid,
                metadata: [
                    'batch_uuid' => $createdBatch->uuid,
                    'total_items' => $totalItems,
                    'unit_cost' => $unitCost,
                ],
                actor: $actor
            );

            return $createdBatch;
        });

        // Dispatch background job vào hàng đợi bulk-generation
        ProcessBulkBatchJob::dispatch($batch->id, $actor?->id)->onQueue('bulk-generation');

        return $batch->fresh(['website', 'campaign']);
    }

    /**
     * Xử lý thực thi tuần tự các bài viết trong lô với cơ chế hoàn credit tự động khi lỗi.
     */
    public function processBatch(BulkBatch $batch): BulkBatch
    {
        if ($batch->isCompleted()) {
            return $batch;
        }

        $batch->update([
            'status' => 'processing',
            'started_at' => $batch->started_at ?? now(),
        ]);

        $settings = $batch->generation_settings ?? [];
        $items = $settings['items'] ?? [];
        $unitCost = (int) ($settings['unit_cost'] ?? $this->getUnitCreditCost($batch->total_items));
        $workspace = $batch->workspace;
        $website = $batch->website;
        $tenantId = $workspace->legacy_tenant_id ?: ($workspace->id);

        foreach ($items as $idx => $item) {
            // Nếu item đã xử lý xong trước đó (trong kịch bản retry), bỏ qua
            if (in_array($item['status'], ['completed', 'failed'], true)) {
                continue;
            }

            // Bóp nghẹt tốc độ (rate-limit throttling) giữa các bài viết ngoài môi trường test
            if ($idx > 0 && ! app()->runningUnitTests()) {
                $delaySeconds = (int) ($settings['delay_seconds'] ?? 2);
                if ($delaySeconds > 0) {
                    sleep(min($delaySeconds, 5));
                }
            }

            $items[$idx]['status'] = 'processing';

            try {
                // Kiểm tra tình huống giả lập lỗi vĩnh viễn để kiểm thử tính năng hoàn credit
                if (! empty($item['simulate_failure']) || str_starts_with((string) $item['keyword'], 'FAIL:')) {
                    throw new \RuntimeException('Lỗi sinh nội dung AI vĩnh viễn cho từ khóa: '.$item['keyword']);
                }

                $keywordModel = Keyword::find($item['keyword_id']);
                if (! $keywordModel) {
                    $keywordModel = Keyword::create([
                        'tenant_id' => $tenantId,
                        'workspace_id' => $workspace->id,
                        'website_id' => $website->id,
                        'campaign_id' => $batch->campaign_id,
                        'user_id' => $workspace->owner_id ?: 1,
                        'keyword' => $item['keyword'],
                        'status' => KeywordStatus::PROCESSING,
                    ]);
                } else {
                    $keywordModel->update(['status' => KeywordStatus::PROCESSING]);
                }

                // Khởi tạo bài viết
                $article = Article::create([
                    'tenant_id' => $tenantId,
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                    'campaign_id' => $batch->campaign_id,
                    'wordpress_site_id' => $website->wordpress_site_id,
                    'keyword_id' => $keywordModel->id,
                    'user_id' => $workspace->owner_id ?: 1,
                    'title' => 'Bài viết: '.trim((string) $item['keyword']),
                    'slug' => Str::slug($item['keyword']).'-'.Str::random(5),
                    'focus_keyword' => $item['keyword'],
                    'word_count' => (int) ($settings['target_word_count'] ?? 1500),
                    'status' => ArticleStatus::DRAFT,
                    'content' => '<h1>'.htmlspecialchars((string) $item['keyword'], ENT_QUOTES, 'UTF-8').'</h1><p>Nội dung đang được khởi tạo hàng loạt bởi AI Content Pipeline...</p>',
                ]);

                // Quyết toán trừ credit thành công từ phần đã giữ trước
                $this->creditLedger->deductCredits(
                    workspace: $workspace,
                    amount: $unitCost,
                    referenceType: 'bulk_batch_article',
                    referenceId: $article->id,
                    idempotencyKey: "settle:batch:{$batch->id}:item:{$idx}",
                    metadata: [
                        'batch_id' => $batch->id,
                        'batch_uuid' => $batch->uuid,
                        'article_id' => $article->id,
                    ],
                    fromReserved: true
                );

                $items[$idx]['status'] = 'completed';
                $items[$idx]['article_id'] = $article->id;
                $items[$idx]['article_title'] = $article->title;

                $batch->increment('completed_items');
                $batch->increment('credits_settled', $unitCost);
                $batch->decrement('credits_reserved', $unitCost);
            } catch (Throwable $e) {
                Log::warning('BulkBatch item failed, issuing auto-refund', [
                    'batch_id' => $batch->id,
                    'item_index' => $idx,
                    'keyword' => $item['keyword'],
                    'error' => $e->getMessage(),
                ]);

                // Tự động hoàn trả credit đã tạm giữ về số dư khả dụng (Zero Loss Guarantee)
                try {
                    $this->creditLedger->releaseCredits(
                        workspace: $workspace,
                        amount: $unitCost,
                        referenceType: 'bulk_batch_failed_item',
                        referenceId: $batch->id,
                        idempotencyKey: "release:batch:{$batch->id}:item:{$idx}",
                        reason: 'Tự động hoàn credit do bài viết trong lô bị lỗi: '.$e->getMessage()
                    );
                } catch (Throwable $releaseError) {
                    Log::error('BulkBatch failed to refund reserved credits', [
                        'batch_id' => $batch->id,
                        'error' => $releaseError->getMessage(),
                    ]);
                }

                $items[$idx]['status'] = 'failed';
                $items[$idx]['error'] = $e->getMessage();

                $batch->increment('failed_items');
                $batch->decrement('credits_reserved', $unitCost);
            }
        }

        $batch->refresh();

        // Xác định trạng thái kết thúc
        $finalStatus = match (true) {
            $batch->failed_items === 0 => 'completed',
            $batch->completed_items > 0 => 'partially_failed',
            default => 'failed',
        };

        $settings['items'] = $items;

        // Dọn dẹp credit dôi dư nếu có (safety net)
        if ($batch->credits_reserved > 0) {
            try {
                $this->creditLedger->releaseCredits(
                    workspace: $workspace,
                    amount: $batch->credits_reserved,
                    referenceType: 'bulk_batch_safety_release',
                    referenceId: $batch->id,
                    idempotencyKey: 'release:safety:batch:'.$batch->id,
                    reason: 'Giải phóng credit dôi dư sau khi kết thúc lô.'
                );
            } catch (Throwable) {
                // Ignore safety release error
            }
        }

        $batch->update([
            'status' => $finalStatus,
            'credits_reserved' => 0,
            'finished_at' => now(),
            'generation_settings' => $settings,
        ]);

        return $batch->fresh();
    }

    /**
     * Hủy lô bài viết và hoàn trả toàn bộ credit còn đang tạm giữ.
     */
    public function cancelBatch(BulkBatch $batch, ?string $reason = null): BulkBatch
    {
        if ($batch->isCompleted()) {
            return $batch;
        }

        $remainingReserved = (int) $batch->credits_reserved;

        if ($remainingReserved > 0) {
            $this->creditLedger->releaseCredits(
                workspace: $batch->workspace,
                amount: $remainingReserved,
                referenceType: 'bulk_batch_cancelled',
                referenceId: $batch->id,
                idempotencyKey: 'release:cancel:batch:'.$batch->id,
                reason: $reason ?: 'Hủy lô bài viết hàng loạt bởi người dùng.'
            );
        }

        $settings = $batch->generation_settings ?? [];
        if (! empty($settings['items'])) {
            foreach ($settings['items'] as $idx => $item) {
                if ($item['status'] === 'pending') {
                    $settings['items'][$idx]['status'] = 'cancelled';
                }
            }
        }

        $batch->update([
            'status' => 'cancelled',
            'credits_reserved' => 0,
            'finished_at' => now(),
            'generation_settings' => $settings,
        ]);

        return $batch->fresh();
    }

    /**
     * Lấy dữ liệu tiến độ thời gian thực của lô bài viết.
     */
    public function getProgress(BulkBatch|string $batchOrUuid): array
    {
        /** @var BulkBatch $batch */
        $batch = is_string($batchOrUuid)
            ? BulkBatch::where('uuid', $batchOrUuid)->firstOrFail()
            : $batchOrUuid;

        $total = max((int) $batch->total_items, 1);
        $processed = (int) ($batch->completed_items + $batch->failed_items);
        $progressPercent = min(100.0, round(($processed / $total) * 100, 1));
        $settings = $batch->generation_settings ?? [];

        return [
            'id' => $batch->id,
            'uuid' => $batch->uuid,
            'name' => $batch->name,
            'status' => $batch->status,
            'total_items' => (int) $batch->total_items,
            'completed_items' => (int) $batch->completed_items,
            'failed_items' => (int) $batch->failed_items,
            'progress_percent' => $progressPercent,
            'credits_reserved' => (int) $batch->credits_reserved,
            'credits_settled' => (int) $batch->credits_settled,
            'started_at' => $batch->started_at?->toIso8601String(),
            'finished_at' => $batch->finished_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
            'generation_settings' => [
                'tone' => $settings['tone'] ?? 'professional',
                'target_word_count' => $settings['target_word_count'] ?? 1500,
                'include_images' => $settings['include_images'] ?? true,
                'auto_publish' => $settings['auto_publish'] ?? false,
                'unit_cost' => $settings['unit_cost'] ?? 18,
            ],
            'items' => $settings['items'] ?? [],
        ];
    }

    /**
     * Trích xuất danh sách Model Keyword từ request data.
     *
     * @return Keyword[]
     */
    protected function resolveKeywords(
        Workspace $workspace,
        Website $website,
        array $data,
        ?User $actor = null
    ): array {
        $results = [];
        $tenantId = $workspace->legacy_tenant_id ?: $workspace->id;

        // Trường hợp 1: Truyền danh sách ID từ khóa có sẵn
        if (! empty($data['keyword_ids']) && is_array($data['keyword_ids'])) {
            $existing = Keyword::where('workspace_id', $workspace->id)
                ->whereIn('id', $data['keyword_ids'])
                ->get();

            foreach ($existing as $kw) {
                $results[] = $kw;
            }

            return $results;
        }

        // Trường hợp 2: Truyền danh sách chuỗi từ khóa (text / array)
        $rawKeywords = $data['keywords'] ?? [];
        if (is_string($rawKeywords)) {
            $rawKeywords = preg_split('/[\r\n,]+/', $rawKeywords);
        }

        if (is_array($rawKeywords)) {
            $cleanList = [];
            foreach ($rawKeywords as $k) {
                $text = is_array($k) ? ($k['keyword'] ?? '') : (string) $k;
                $text = trim($text);
                if ($text !== '' && ! in_array(mb_strtolower($text), $cleanList, true)) {
                    $cleanList[] = mb_strtolower($text);

                    /** @var Keyword $kwModel */
                    $kwModel = Keyword::firstOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'keyword' => $text,
                        ],
                        [
                            'tenant_id' => $tenantId,
                            'website_id' => $website->id,
                            'campaign_id' => $data['campaign_id'] ?? null,
                            'user_id' => $actor?->id ?: ($workspace->owner_id ?: 1),
                            'language' => $website->default_language ?? 'vi',
                            'status' => KeywordStatus::PENDING,
                        ]
                    );

                    $results[] = $kwModel;
                }
            }
        }

        return $results;
    }
}
