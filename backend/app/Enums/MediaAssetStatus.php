<?php

namespace App\Enums;

enum MediaAssetStatus: string
{
    case PENDING = 'pending';
    case DOWNLOADED = 'downloaded';
    case UPLOADED = 'uploaded';
    case FAILED = 'failed';
}
