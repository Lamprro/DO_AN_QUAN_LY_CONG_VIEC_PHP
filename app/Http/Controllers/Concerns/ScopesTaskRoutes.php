<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;

/** Tasks are routed both as /workspaces/{w}/tasks/{t} (personal) and /workspaces/{w}/projects/{p}/tasks/{t}. */
trait ScopesTaskRoutes
{
    /** The bound task must belong to exactly this workspace/project scope. */
    protected function assertTaskInScope(Workspace $workspace, ?Project $project, Task $task): void
    {
        abort_unless($task->workspace_id === $workspace->id && $task->project_id === $project?->id, 404);
    }

    protected function taskRoute(?Project $project, string $action): string
    {
        return ($project ? 'workspaces.projects.tasks.' : 'workspaces.tasks.').$action;
    }

    protected function taskRouteParams(Workspace $workspace, ?Project $project, ?Task $task = null): array
    {
        return array_values(array_filter([$workspace, $project, $task]));
    }
}
