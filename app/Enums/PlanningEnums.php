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

enum ReportScope: string
{
    case PERSONAL = 'personal';
    case TEAM = 'team';
    case PROJECT = 'project';
}
