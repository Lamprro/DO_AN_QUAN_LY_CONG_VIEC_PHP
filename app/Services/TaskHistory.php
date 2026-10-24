<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

class TaskHistory
{
    public function record(User $actor, Task $task, string $event, array $data = []): TaskActivity
    {
        return app(TaskService::class)->recordHistory($actor, $task, $event, $data);
    }
}
