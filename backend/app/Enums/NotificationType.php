<?php

namespace App\Enums;

enum NotificationType: string
{
    case ARTICLE_REVIEW_REQUESTED = 'article_review_requested';
    case ARTICLE_APPROVED = 'article_approved';
    case ARTICLE_REJECTED = 'article_rejected';
    case ARTICLE_PUBLISHED = 'article_published';
    case ARTICLE_PUBLISH_FAILED = 'article_publish_failed';
    case CAMPAIGN_COMPLETED = 'campaign_completed';
    case CAMPAIGN_FAILED = 'campaign_failed';
    case CAMPAIGN_PAUSED_QUOTA = 'campaign_paused_quota';
    case QUOTA_ARTICLES_WARNING = 'quota_articles_warning';
    case QUOTA_ARTICLES_EXCEEDED = 'quota_articles_exceeded';
    case QUOTA_COST_WARNING = 'quota_cost_warning';
    case QUOTA_COST_EXCEEDED = 'quota_cost_exceeded';
    case BILLING_CYCLE_RESET = 'billing_cycle_reset';
    case ARTICLE_QA_FAILED = 'article_qa_failed';
    case ARTICLE_QA_PASSED = 'article_qa_passed';
    case WORDPRESS_CONNECTION_FAILED = 'wordpress_connection_failed';
    case SERPER_QUOTA_WARNING = 'serper_quota_warning';
}
