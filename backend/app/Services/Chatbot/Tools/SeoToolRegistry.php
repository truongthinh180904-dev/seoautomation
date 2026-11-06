<?php

namespace App\Services\Chatbot\Tools;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Chatbot\Tools\Adapters\AiImageToolAdapter;
use App\Services\Chatbot\Tools\Adapters\BulkGenerationAdapter;
use App\Services\Chatbot\Tools\Adapters\CompetitorAnalysisAdapter;
use App\Services\Chatbot\Tools\Adapters\ContentGapAdapter;
use App\Services\Chatbot\Tools\Adapters\ContentStudioWriteAdapter;
use App\Services\Chatbot\Tools\Adapters\InternalLinksAdapter;
use App\Services\Chatbot\Tools\Adapters\KeywordResearchAdapter;
use App\Services\Chatbot\Tools\Adapters\SeoScoreAdapter;
use App\Services\Chatbot\Tools\Adapters\StockImageToolAdapter;
use App\Services\Chatbot\Tools\Adapters\TopicClusterAdapter;
use App\Services\Chatbot\Tools\Adapters\WordPressPublishAdapter;
use App\Services\Chatbot\Tools\Contracts\ChatToolInterface;
use InvalidArgumentException;

class SeoToolRegistry
{
    /**
     * Danh sách các instance công cụ đã được đăng ký.
     *
     * @var array<string, ChatToolInterface>
     */
    protected array $tools = [];

    /**
     * Mảng các class Adapter mặc định kết nối 10+ công cụ SEO sẵn có từ Phase 1 và Phase 2.
     *
     * @var array<int, class-string<ChatToolInterface>>
     */
    protected const DEFAULT_ADAPTERS = [
        KeywordResearchAdapter::class,
        CompetitorAnalysisAdapter::class,
        TopicClusterAdapter::class,
        ContentGapAdapter::class,
        ContentStudioWriteAdapter::class,
        SeoScoreAdapter::class,
        InternalLinksAdapter::class,
        StockImageToolAdapter::class,
        AiImageToolAdapter::class,
        BulkGenerationAdapter::class,
        WordPressPublishAdapter::class,
    ];

    public function __construct(array $initialTools = [])
    {
        if (! empty($initialTools)) {
            foreach ($initialTools as $tool) {
                if ($tool instanceof ChatToolInterface) {
                    $this->register($tool);
                }
            }
        } else {
            $this->registerDefaults();
        }
    }

    /**
     * Đăng ký tự động danh sách Adapter mặc định qua Service Container của Laravel.
     */
    public function registerDefaults(): self
    {
        foreach (self::DEFAULT_ADAPTERS as $adapterClass) {
            if (class_exists($adapterClass)) {
                $tool = app($adapterClass);
                if ($tool instanceof ChatToolInterface) {
                    $this->register($tool);
                }
            }
        }

        return $this;
    }

    /**
     * Đăng ký một công cụ SEO vào Registry.
     */
    public function register(ChatToolInterface $tool): self
    {
        $this->tools[$tool->getName()] = $tool;

        return $this;
    }

    /**
     * Kiểm tra xem một công cụ đã được đăng ký hay chưa.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Lấy ra instance của công cụ theo tool name.
     */
    public function get(string $name): ?ChatToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Lấy toàn bộ danh sách các công cụ đã đăng ký.
     *
     * @return array<string, ChatToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Đếm tổng số công cụ SEO đã đăng ký trong hệ thống.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Lấy danh mục định nghĩa chuẩn hóa của toàn bộ các tools (để đưa vào AI System Prompt hoặc Function Calling).
     *
     * @return array<string, array{
     *     name: string,
     *     title: string,
     *     description: string,
     *     estimated_credits: int,
     *     is_sensitive: bool,
     *     parameters: array<string, mixed>
     * }>
     */
    public function getDefinitions(): array
    {
        $definitions = [];
        foreach ($this->tools as $name => $tool) {
            $definitions[$name] = [
                'name' => $tool->getName(),
                'title' => $tool->getTitle(),
                'description' => $tool->getDescription(),
                'estimated_credits' => $tool->getEstimatedCredits(),
                'is_sensitive' => $tool->isSensitive(),
                'parameters' => $tool->getParametersSchema(),
            ];
        }

        return $definitions;
    }

    /**
     * Thực thi một công cụ cụ thể theo tên kèm cô lập Multi-Tenant theo Workspace.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function execute(string $toolName, Workspace $workspace, array $params = [], ?User $actor = null): array
    {
        $tool = $this->get($toolName);
        if (! $tool) {
            throw new InvalidArgumentException("Công cụ SEO '{$toolName}' không tồn tại hoặc chưa được đăng ký trong hệ thống.");
        }

        return $tool->execute($workspace, $params, $actor);
    }
}
