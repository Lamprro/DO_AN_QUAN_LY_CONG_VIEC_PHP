<?php

namespace App\Enums;

enum CalendarEventStatus: string
{
    case PLANNED = 'planned';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
