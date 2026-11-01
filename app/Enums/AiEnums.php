<?php

namespace App\Enums;

enum AiCallStatus: string
{
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}

enum AiOperation: string
{
    case PLANNING = 'planning';
    case REPLANNING = 'replanning';
    case ASSISTANCE = 'assistance';
    case REPORT = 'report';
}

enum AiRunStatus: string
{
    case QUEUED = 'queued';
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
