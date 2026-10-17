<?php

namespace App\Enums;

enum CalendarVisibility: string
{
    case PRIVATE = 'private';
    case WORKSPACE = 'workspace';
    case PROJECT = 'project';
}
