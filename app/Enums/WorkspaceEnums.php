<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case LEFT = 'left';
}

enum ProjectStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

enum UserStatus: string
{
    case ACTIVE = 'active';
    case DISABLED = 'disabled';
}

enum WorkspaceKind: string
{
    case PERSONAL = 'personal';
    case TEAM = 'team';
}
