<?php

namespace App\Services\Chatbot\Tools\Contracts;

use App\Models\User;
use App\Models\Workspace;

interface ChatToolInterface
{
    /**
     * Tên định danh duy nhất của công cụ (Tool Key), ví dụ: 'competitor_analysis'.
     */
    public function getName(): string;

    /**
     * Tên hiển thị thân thiện với người dùng.
     */
    public function getTitle(): string;

    /**
     * Mô tả chi tiết chức năng của công cụ.
     */
    public function getDescription(): string;

    /**
     * Ước tính số credit cần tiêu thụ khi thực thi công cụ.
     *
     * @param  array<string, mixed>  $params
     */
    public function getEstimatedCredits(array $params = []): int;

    /**
     * Xác định công cụ có tính chất nhạy cảm (cần duyệt Human-in-the-Loop) hay không.
     */
    public function isSensitive(): bool;

    /**
     * Định nghĩa schema tham số đầu vào của công cụ (phục vụ validate và LLM function calling).
     *
     * @return array<string, mixed>
     */
    public function getParametersSchema(): array;

    /**
     * Thực thi nghiệp vụ của công cụ gắn chặt với Workspace để đảm bảo cô lập Multi-Tenant.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(Workspace $workspace, array $params = [], ?User $actor = null): array;
}
