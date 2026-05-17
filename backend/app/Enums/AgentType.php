<?php

namespace App\Enums;

enum AgentType: string
{
    case SERP_RESEARCH = 'serp_research';
    case COMPETITOR_ANALYSIS = 'competitor_analysis';
    case CONTENT_ANALYSIS = 'content_analysis';
    case OUTLINE = 'outline';
    case WRITING = 'writing';
    case SEO_OPTIMIZATION = 'seo_optimization';
    case INTERNAL_LINKING = 'internal_linking';
    case QA_VALIDATION = 'qa_validation';
    case PUBLISHING = 'publishing';

    public function label(): string
    {
        return match($this) {
            self::SERP_RESEARCH => 'Nghiên cứu SERP',
            self::COMPETITOR_ANALYSIS => 'Phân tích đối thủ',
            self::CONTENT_ANALYSIS => 'Phân tích nội dung',
            self::OUTLINE => 'Lên dàn ý',
            self::WRITING => 'Viết bài',
            self::SEO_OPTIMIZATION => 'Tối ưu SEO',
            self::INTERNAL_LINKING => 'Liên kết nội bộ',
            self::QA_VALIDATION => 'Kiểm tra chất lượng',
            self::PUBLISHING => 'Đăng bài',
        };
    }

    public function queue(): string
    {
        return match($this) {
            self::SERP_RESEARCH,
            self::COMPETITOR_ANALYSIS,
            self::CONTENT_ANALYSIS => 'ai-research',
            
            self::OUTLINE,
            self::WRITING,
            self::SEO_OPTIMIZATION,
            self::QA_VALIDATION,
            self::INTERNAL_LINKING => 'ai-writing',
            
            self::PUBLISHING => 'publishing',
        };
    }
}
