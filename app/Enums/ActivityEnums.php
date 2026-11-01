<?php

namespace App\Enums;

enum ActivitySource: string
{
    case USER = 'user';
    case AI = 'ai';
    case GITHUB = 'github';
    case SYSTEM = 'system';
}

enum ActivityType: string
{
    case COMMENT = 'COMMENT';
    case HISTORY = 'HISTORY';
    case ATTACHMENT = 'ATTACHMENT';
}
