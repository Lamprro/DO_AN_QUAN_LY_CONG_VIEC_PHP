<?php

namespace App\Enums;

enum GithubLogStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
    case IGNORED = 'ignored';
}

enum IntegrationProvider: string
{
    case GITHUB = 'github';
    case GOOGLE_CALENDAR = 'google_calendar';
    case TELEGRAM = 'telegram';
}

enum IntegrationStatus: string
{
    case DISABLED = 'disabled';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
}
