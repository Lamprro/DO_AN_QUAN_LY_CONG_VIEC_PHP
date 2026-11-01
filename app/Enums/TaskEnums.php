<?php

namespace App\Enums;

enum BlockerStatus: string
{
    case OPEN = 'open';
    case RESOLVED = 'resolved';
}

enum DependencyType: string
{
    case FS = 'FS';
}

enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';
}

enum TaskStatus: string
{
    case TODO = 'TODO';
    case IN_PROGRESS = 'IN_PROGRESS';
    case REVIEW = 'REVIEW';
    case DONE = 'DONE';
    case CANCELLED = 'CANCELLED';
}

enum TaskType: string
{
    case GROUP = 'GROUP';
    case WORK = 'WORK';
    case MILESTONE = 'MILESTONE';
}
