<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;

class TaskEditData
{
    public function presets(Workspace $workspace): array
    {
        return app(TaskService::class)->presets($workspace);
    }

    public function forDate(CarbonImmutable $date): array
    {
        return app(TaskService::class)->presetsForDate($date);
    }
}
