<?php

namespace App\Services\Chatbot\Tools\Adapters;

use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Bulk\BulkGenerationService;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use InvalidArgumentException;

class BulkGenerationAdapter implements ChatToolInterface
{
    public function __construct(
        protected BulkGenerationService $bulkService
    ) {}

    public function getName(): string
    {
        return 'bulk_generation';
    }

    public function getTitle(): string
    {
        return 'Sản xuất Nội dung Hàng loạt (Bulk Generation)';
    }

    public function getDescription(): string
    {
        return 'Tạo hàng loạt bài viết theo danh sách từ khóa lớn qua hàng đợi Redis Queue chuyên dụng (Hành động nhạy cảm tiêu hao nhiều credit).';
    }

    public function getEstimatedCredits(array $params = []): int
    {
        $keywords = $this->resolveKeywordsList($params['keywords'] ?? []);
        $count = max(1, count($keywords));

        return $this->bulkService->calculateTotalCredits($count);
    }

    public function isSensitive(): bool
    {
        return true; // Bắt buộc phê duyệt Human-in-the-Loop
    }

    public function getParametersSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['keywords'],
            'properties' => [
                'keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Danh sách từ khóa cần tạo bài hàng loạt (mảng string hoặc chuỗi ngăn cách bởi dấu xuống dòng).',
                ],
                'website_id' => [
                    'type' => 'integer',
                    'description' => 'ID Website mục tiêu thuộc Workspace.',
                ],
                'language' => [
                    'type' => 'string',
                    'default' => 'vi',
                    'description' => 'Ngôn ngữ bài viết (mặc định: vi).',
                ],
            ],
        ];
    }

    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $keywordList = $this->resolveKeywordsList($params['keywords'] ?? []);
        if (empty($keywordList)) {
            throw new InvalidArgumentException('Danh sách từ khóa sản xuất hàng loạt không được để trống.');
        }

        $websiteId = ! empty($params['website_id']) ? (int) $params['website_id'] : null;
        $website = null;
        if ($websiteId) {
            $website = Website::where('workspace_id', $workspace->id)->find($websiteId);
        }
        if (! $website) {
            $website = Website::where('workspace_id', $workspace->id)->first();
        }

        if (! $website) {
            throw new InvalidArgumentException('Workspace chưa có website nào để tạo bài hàng loạt. Hãy cấu hình website trước.');
        }

        $payload = [
            'keywords' => $keywordList,
            'source_mode' => 'custom',
            'language' => $params['language'] ?? 'vi',
            'article_settings' => [
                'language' => $params['language'] ?? 'vi',
                'auto_generate_images' => true,
            ],
        ];

        $batch = $this->bulkService->createBatch(
            workspace: $workspace,
            website: $website,
            data: $payload,
            actor: $actor
        );

        return [
            'tool' => $this->getName(),
            'batch_id' => $batch->id,
            'website_id' => $website->id,
            'website_name' => $website->name,
            'total_items' => $batch->total_items,
            'total_credits' => $batch->total_credits,
            'status' => $batch->status,
            'is_sensitive' => true,
        ];
    }

    /**
     * Chuẩn hóa danh sách từ khóa từ mảng hoặc chuỗi phân tách.
     *
     * @return array<int, string>
     */
    protected function resolveKeywordsList(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw)));
        }

        if (is_string($raw)) {
            $parts = preg_split('/[\r\n,]+/', $raw);

            return array_values(array_filter(array_map('trim', $parts ?: [])));
        }

        return [];
    }
}
