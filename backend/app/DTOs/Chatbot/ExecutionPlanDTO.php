<?php

namespace App\DTOs\Chatbot;

use Spatie\LaravelData\Data;

class ExecutionPlanDTO extends Data
{
    /**
     * @param  string  $id  Mã UUID định danh kế hoạch thực thi
     * @param  string  $intent  Ý định tương ứng
     * @param  string  $title  Tiêu đề kế hoạch thực thi
     * @param  string  $summary  Tóm tắt các bước hành động
     * @param  array<int, array{
     *     step_number: int,
     *     tool: string,
     *     action: string,
     *     description: string,
     *     params: array<string, mixed>,
     *     status: string,
     *     estimated_credits: int,
     *     requires_approval: bool
     * }>  $steps  Danh sách các bước thực thi chi tiết
     * @param  bool  $requiresApproval  Có chứa hành động nhạy cảm đòi hỏi Human Approval hay không
     * @param  int  $totalEstimatedCredits  Tổng credit dự kiến tiêu thụ
     * @param  bool  $canExecuteImmediately  Có thể chạy ngay mà không cần chờ xác nhận
     * @param  bool  $clarificationNeeded  Kế hoạch đang bị tạm giữ để làm rõ thông tin
     * @param  string|null  $clarificationMessage  Nội dung câu hỏi làm rõ nếu có
     * @param  array<string, mixed>  $contextMetadata  Dữ liệu ngữ cảnh bổ sung (brand, website, article, v.v.)
     */
    public function __construct(
        public string $id,
        public string $intent,
        public string $title,
        public string $summary,
        public array $steps = [],
        public bool $requiresApproval = false,
        public int $totalEstimatedCredits = 0,
        public bool $canExecuteImmediately = true,
        public bool $clarificationNeeded = false,
        public ?string $clarificationMessage = null,
        public array $contextMetadata = [],
    ) {}

    /**
     * Đếm tổng số bước trong kế hoạch.
     */
    public function countSteps(): int
    {
        return count($this->steps);
    }

    /**
     * Kiểm tra kế hoạch có hành động nhạy cảm cần phê duyệt hay không.
     */
    public function hasSensitiveActions(): bool
    {
        return $this->requiresApproval;
    }

    /**
     * Tính tổng credit ước tính từ danh sách các bước.
     */
    public function recalculateTotalCredits(): int
    {
        $sum = 0;
        foreach ($this->steps as $step) {
            $sum += (int) ($step['estimated_credits'] ?? 0);
        }

        $this->totalEstimatedCredits = $sum;

        return $sum;
    }
}
