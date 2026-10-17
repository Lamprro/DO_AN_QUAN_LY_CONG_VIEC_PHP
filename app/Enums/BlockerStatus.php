<?php

namespace App\Enums;

enum BlockerStatus: string
{
    case OPEN = 'open';
    case RESOLVED = 'resolved';
}
