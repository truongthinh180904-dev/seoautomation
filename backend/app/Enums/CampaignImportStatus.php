<?php

namespace App\Enums;

enum CampaignImportStatus: string
{
    case UPLOADED = 'uploaded';
    case VALIDATED = 'validated';
    case IMPORTED = 'imported';
    case FAILED = 'failed';
}
