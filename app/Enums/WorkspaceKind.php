<?php

namespace App\Enums;

enum WorkspaceKind: string
{
    case PERSONAL = 'personal';
    case TEAM = 'team';
}
