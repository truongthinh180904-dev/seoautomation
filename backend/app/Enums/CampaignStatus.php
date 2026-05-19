<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case DRAFT = 'draft';
    case IMPORTED = 'imported';
    case PLANNING = 'planning';
    case READY = 'ready';
    case PROCESSING = 'processing';
    case REVIEWING = 'reviewing';
    case PUBLISHING = 'publishing';
    case COMPLETED = 'completed';
    case PAUSED = 'paused';
    case FAILED = 'failed';
}
