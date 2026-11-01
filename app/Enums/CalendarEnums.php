<?php

namespace App\Enums;

enum CalendarEventStatus: string
{
    case PLANNED = 'planned';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

enum CalendarEventType: string
{
    case NOTE = 'NOTE';
    case MEETING = 'MEETING';
    case BUSY = 'BUSY';
    case EXTRA_AVAILABLE = 'EXTRA_AVAILABLE';
    case TASK_BLOCK = 'TASK_BLOCK';
}

enum CalendarVisibility: string
{
    case PRIVATE = 'private';
    case WORKSPACE = 'workspace';
    case PROJECT = 'project';
}
