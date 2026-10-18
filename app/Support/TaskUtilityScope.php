<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;

class TaskUtilityScope
{
    public static function check(User $actor, Workspace $workspace, ?Project $project): void
    {
        abort_if($project && $project->workspace_id !== $workspace->id, 404);
        abort_unless(app(TaskPolicy::class)->scope($actor, $workspace, $project, 'task.view'), 403);
    }

    public static function url(Workspace $workspace, ?Project $project, array $query = []): string
    {
        return route($project ? 'workspaces.projects.tasks.index' : 'workspaces.tasks.index', array_merge($project ? [$workspace, $project] : [$workspace], $query));
    }
}
