<?php

namespace App\Enums;

enum TaskType: string
{
    case GROUP = 'GROUP';
    case WORK = 'WORK';
    case MILESTONE = 'MILESTONE';
}
