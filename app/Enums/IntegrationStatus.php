<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case DISABLED = 'disabled';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
}
