<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBlocker;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ProjectAccess;
use App\Support\WorkspaceAccess;

class TaskPolicy
{
    public function __construct(private ProjectAccess $projects, private WorkspaceAccess $workspaces) {}

    public function projectWritable(?Project $project): bool
    {
        return ! $project || (! $project->archived_at && ! in_array($project->status->value, ['completed', 'cancelled'], true));
    }

    public function scope(User $user, Workspace $workspace, ?Project $project, string $permission): bool
    {
        if ($project) {
            return $project->workspace_id === $workspace->id && $this->projects->allows($user, $project, 'task.view') && $this->projects->allows($user, $project, $permission);
        }

        return $workspace->kind->value === 'personal' && $workspace->owner_id === $user->id
            && $this->workspaces->allows($user, $workspace, $permission);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->scope($user, $task->workspace, $task->project, 'task.view');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->mutable($user, $task, 'task.update') && ($task->task_type->value === 'GROUP' || in_array($task->status->value, ['TODO', 'IN_PROGRESS'], true));
    }

    public function mutable(User $user, Task $task, string $permission): bool
    {
        if ($task->archived_at || ! $this->projectWritable($task->project) || ! $this->scope($user, $task->workspace, $task->project, $permission)) {
            return false;
        }
        $seen = [];
        $parent = $task->parent;
        while ($parent && ! isset($seen[$parent->id])) {
            if ($parent->archived_at) {
                return false;
            }$seen[$parent->id] = true;
            $parent = $parent->parent;
        }

        return true;
    }

    public function execute(User $user, Task $task): bool
    {
        return $this->mutable($user, $task, 'task.update') && ($task->assignee_id === $user->id || $this->scope($user, $task->workspace, $task->project, 'task.assign'));
    }

    public function review(User $user, Task $task): bool
    {
        return $this->mutable($user, $task, 'task.review') && ($task->reviewer_id === $user->id || $task->workspace->owner_id === $user->id);
    }

    public function link(User $user, Task $task): bool
    {
        return $this->mutable($user, $task, 'task.assign');
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->mutable($user, $task, 'task.comment');
    }

    public function attach(User $user, Task $task): bool
    {
        return $this->mutable($user, $task, 'task.attach');
    }

    public function block(User $user, Task $task): bool
    {
        return $this->mutable($user, $task, 'task.update');
    }

    public function resolveBlocker(User $user, Task $task, TaskBlocker $blocker): bool
    {
        return $blocker->task_id === $task->id && $this->block($user, $task)
            && $task->task_type->value !== 'GROUP' && ! in_array($task->status->value, ['DONE', 'CANCELLED'], true)
            && ($blocker->reported_by === $user->id || $this->scope($user, $task->workspace, $task->project, 'task.assign') || $this->review($user, $task));
    }

    private function parentsWritable(Task $task): bool
    {
        $seen = [];
        $parent = $task->parent;
        while ($parent && ! isset($seen[$parent->id])) {
            if ($parent->archived_at) {
                return false;
            }$seen[$parent->id] = true;
            $parent = $parent->parent;
        }

        return true;
    }

    public function archive(User $user, Task $task): bool
    {
        return $this->projectWritable($task->project) && $this->parentsWritable($task) && $this->scope($user, $task->workspace, $task->project, 'task.archive');
    }

    public function delete(User $user, Task $task): bool
    {
        return ! $task->archived_at && $this->archive($user, $task);
    }
}
