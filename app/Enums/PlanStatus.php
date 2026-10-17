<?php

namespace App\Enums;

enum PlanStatus: string
{
    case DRAFT = 'draft';
    case PROPOSED = 'proposed';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUPERSEDED = 'superseded';
}
