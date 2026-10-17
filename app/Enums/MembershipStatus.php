<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case LEFT = 'left';
}
