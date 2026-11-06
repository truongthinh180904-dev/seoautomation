<?php

namespace App\DTOs\Chatbot;

use Spatie\LaravelData\Data;

class UserIntentDTO extends Data
{
    public const INTENT_KEYWORD_RESEARCH = 'keyword_research';

    public const INTENT_COMPETITOR_ANALYSIS = 'competitor_analysis';

    public const INTENT_TOPIC_CLUSTER = 'topic_cluster';

    public const INTENT_ARTICLE_WRITE = 'article_write';

    public const INTENT_ARTICLE_REWRITE = 'article_rewrite';

    public const INTENT_SEO_SCORE = 'seo_score';

    public const INTENT_BULK_GENERATION = 'bulk_generation';

    public const INTENT_PUBLISH_WORDPRESS = 'publish_wordpress';

    public const INTENT_GENERAL_CHAT = 'general_chat';

    /**
     * Danh sách 8 ý định nghiệp vụ SEO cốt lõi của Aura Assistant.
     *
     * @var list<string>
     */
    public const CORE_INTENTS = [
        self::INTENT_KEYWORD_RESEARCH,
        self::INTENT_COMPETITOR_ANALYSIS,
        self::INTENT_TOPIC_CLUSTER,
        self::INTENT_ARTICLE_WRITE,
        self::INTENT_ARTICLE_REWRITE,
        self::INTENT_SEO_SCORE,
        self::INTENT_BULK_GENERATION,
        self::INTENT_PUBLISH_WORDPRESS,
    ];

    /**
     * @param  string  $intent  Tên ý định chính được nhận diện
     * @param  float  $confidence  Độ tự tin phân loại (0.0 đến 1.0)
     * @param  array<string, mixed>  $parameters  Các tham số đã trích xuất từ câu lệnh
     * @param  bool  $needsClarification  Có cần làm rõ do thiếu thông tin thiết yếu không
     * @param  list<string>  $missingFields  Danh sách trường còn thiếu cần người dùng bổ sung
     * @param  string|null  $clarificationQuestion  Câu hỏi làm rõ lịch sự gửi tới người dùng
     * @param  list<string>  $suggestedActions  Gợi ý câu lệnh hoặc hành động tiếp theo
     * @param  string  $rawQuery  Câu yêu cầu gốc của người dùng
     */
    public function __construct(
        public string $intent,
        public float $confidence = 1.0,
        public array $parameters = [],
        public bool $needsClarification = false,
        public array $missingFields = [],
        public ?string $clarificationQuestion = null,
        public array $suggestedActions = [],
        public string $rawQuery = '',
    ) {}

    /**
     * Kiểm tra độ tin cậy của phân loại ý định có đạt ngưỡng hay không.
     */
    public function isConfident(float $threshold = 0.7): bool
    {
        return $this->confidence >= $threshold;
    }

    /**
     * Kiểm tra một tham số có tồn tại và khác rỗng hay không.
     */
    public function hasParameter(string $key): bool
    {
        if (! array_key_exists($key, $this->parameters)) {
            return false;
        }

        $val = $this->parameters[$key];

        if ($val === null || $val === '') {
            return false;
        }

        if (is_array($val) && empty($val)) {
            return false;
        }

        return true;
    }

    /**
     * Lấy giá trị tham số an toàn với giá trị mặc định.
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * Kiểm tra xem ý định này có thuộc 8 ý định SEO cốt lõi hay không.
     */
    public function isCoreSeoIntent(): bool
    {
        return in_array($this->intent, self::CORE_INTENTS, true);
    }
}
