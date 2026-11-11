<?php

namespace App\Authorization;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

/**
 * Task scope rules shared by TaskPolicy, services and read models.
 * A task lives either in a project or (personal workspace only) without one.
 */
class TaskAccess
{
    public function __construct(private ProjectAccess $projects, private WorkspaceAccess $workspaces) {}

    /** Does the user hold $permission for tasks in this workspace/project scope? */
    public function scope(User $user, Workspace $workspace, ?Project $project, string $permission): bool
    {
        if ($project) {
            return $project->workspace_id === $workspace->id && $this->projects->allows($user, $project, 'task.view') && $this->projects->allows($user, $project, $permission);
        }

        return $workspace->kind->value === 'personal' && $workspace->owner_id === $user->id && $this->workspaces->allows($user, $workspace, $permission);
    }

    /** Archived, completed or cancelled projects are read-only. */
    public function projectWritable(?Project $project): bool
    {
        return ! $project || ! $project->archived_at && ! in_array($project->status->value, ['completed', 'cancelled'], true);
    }

    public function ancestorsWritable(Task $task): bool
    {
        $seen = [];
        $parent = $task->parent;
        while ($parent && ! isset($seen[$parent->id])) {
            if ($parent->archived_at) {
                return false;
            }
            $seen[$parent->id] = true;
            $parent = $parent->parent;
        }

        return true;
    }

    /** The task itself may be changed by the user with $permission. */
    public function mutable(User $user, Task $task, string $permission): bool
    {
        return ! $task->archived_at && $this->projectWritable($task->project) && $this->ancestorsWritable($task) && $this->scope($user, $task->workspace, $task->project, $permission);
    }

    /** Templates: read needs task.view; write additionally needs create + assign on a writable scope. */
    public function templates(User $user, Workspace $workspace, ?Project $project, bool $write = false): bool
    {
        return $this->scope($user, $workspace, $project, 'task.view') && (! $write || $this->projectWritable($project) && $this->scope($user, $workspace, $project, 'task.create') && $this->scope($user, $workspace, $project, 'task.assign'));
    }
}
