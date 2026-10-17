<?php

namespace App\Enums;

enum ActivityType: string
{
    case COMMENT = 'COMMENT';
    case HISTORY = 'HISTORY';
    case ATTACHMENT = 'ATTACHMENT';
}
