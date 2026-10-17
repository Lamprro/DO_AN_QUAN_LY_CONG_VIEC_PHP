<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case GITHUB = 'github';
    case GOOGLE_CALENDAR = 'google_calendar';
    case TELEGRAM = 'telegram';
}
