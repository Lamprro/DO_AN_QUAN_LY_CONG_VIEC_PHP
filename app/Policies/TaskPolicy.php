<?php

namespace App\Policies;

use App\Authorization\TaskAccess;
use App\Models\Task;
use App\Models\TaskBlocker;
use App\Models\User;

/** Abilities on a single task. Scope rules live in App\Authorization\TaskAccess. */
class TaskPolicy
{
    public function __construct(private TaskAccess $access) {}

    public function view(User $user, Task $task): bool
    {
        return $this->access->scope($user, $task->workspace, $task->project, 'task.view');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->access->mutable($user, $task, 'task.update') && ($task->task_type->value === 'GROUP' || in_array($task->status->value, ['TODO', 'IN_PROGRESS'], true));
    }

    public function execute(User $user, Task $task): bool
    {
        return $this->access->mutable($user, $task, 'task.update') && ($task->assignee_id === $user->id || $this->access->scope($user, $task->workspace, $task->project, 'task.assign'));
    }

    public function review(User $user, Task $task): bool
    {
        return $this->access->mutable($user, $task, 'task.review') && ($task->reviewer_id === $user->id || $task->workspace->owner_id === $user->id);
    }

    public function link(User $user, Task $task): bool
    {
        return $this->access->mutable($user, $task, 'task.assign');
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->access->mutable($user, $task, 'task.comment');
    }

    public function attach(User $user, Task $task): bool
    {
        return $this->access->mutable($user, $task, 'task.attach');
    }

    public function block(User $user, Task $task): bool
    {
        return $this->access->mutable($user, $task, 'task.update');
    }

    public function resolveBlocker(User $user, Task $task, TaskBlocker $blocker): bool
    {
        return $blocker->task_id === $task->id && $this->block($user, $task)
            && $task->task_type->value !== 'GROUP' && ! in_array($task->status->value, ['DONE', 'CANCELLED'], true)
            && ($blocker->reported_by === $user->id || $this->access->scope($user, $task->workspace, $task->project, 'task.assign') || $this->review($user, $task));
    }

    public function archive(User $user, Task $task): bool
    {
        return $this->access->projectWritable($task->project) && $this->access->ancestorsWritable($task) && $this->access->scope($user, $task->workspace, $task->project, 'task.archive');
    }

    public function delete(User $user, Task $task): bool
    {
        return ! $task->archived_at && $this->archive($user, $task);
    }
}
