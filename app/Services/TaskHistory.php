<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

class TaskHistory
{
    public function record(User $actor, Task $task, string $event, array $data = []): TaskActivity
    {
        $activity = TaskActivity::create([
            'task_id' => $task->id,
            'actor_id' => $actor->id,
            'type' => 'HISTORY',
            'source' => 'user',
            'event_type' => $event,
            'data' => $data,
        ]);

        app(NotificationService::class)->taskEvent($activity, $task, $actor, $data);

        return $activity;
    }
}
