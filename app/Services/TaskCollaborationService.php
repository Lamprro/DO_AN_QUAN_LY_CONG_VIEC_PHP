<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

class TaskCollaborationService
{
    public function people(Task $task)
    {
        return app(TaskUtilityService::class)->people($task);
    }

    public function viewData(Task $task): array
    {
        return app(TaskUtilityService::class)->viewData($task);
    }

    public function mentions(Task $task, array $ids): array
    {
        return app(TaskUtilityService::class)->mentions($task, $ids);
    }

    public function watch(User $actor, Task $task, string $userId, bool $remove = false): void
    {
        app(TaskUtilityService::class)->watch($actor, $task, $userId, $remove);
    }
}
