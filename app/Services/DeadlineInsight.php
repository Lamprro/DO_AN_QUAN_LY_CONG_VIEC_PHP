<?php

namespace App\Services;

use App\Models\Task;

class DeadlineInsight
{
    public function build(Task $task): array
    {
        return app(TaskService::class)->deadlineInsight($task);
    }
}
