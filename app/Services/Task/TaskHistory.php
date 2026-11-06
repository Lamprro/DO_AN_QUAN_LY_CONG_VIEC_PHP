<?php

namespace App\Services\Task;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Services\Notification\NotificationService;

class TaskHistory
{
    public function __construct(private NotificationService $notifications) {}

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

        $this->notifications->taskEvent($activity, $task, $actor, $data);

        return $activity;
    }
}
