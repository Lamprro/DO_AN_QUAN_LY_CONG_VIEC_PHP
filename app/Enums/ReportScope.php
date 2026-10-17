<?php

namespace App\Enums;

enum ReportScope: string
{
    case PERSONAL = 'personal';
    case TEAM = 'team';
    case PROJECT = 'project';
}
