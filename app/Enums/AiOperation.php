<?php

namespace App\Enums;

enum AiOperation: string
{
    case PLANNING = 'planning';
    case REPLANNING = 'replanning';
    case ASSISTANCE = 'assistance';
    case REPORT = 'report';
}
