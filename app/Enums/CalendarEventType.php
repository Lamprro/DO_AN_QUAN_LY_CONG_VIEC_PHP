<?php

namespace App\Enums;

enum CalendarEventType: string
{
    case NOTE = 'NOTE';
    case MEETING = 'MEETING';
    case BUSY = 'BUSY';
    case EXTRA_AVAILABLE = 'EXTRA_AVAILABLE';
    case TASK_BLOCK = 'TASK_BLOCK';
}
