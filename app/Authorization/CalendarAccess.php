<?php

namespace App\Authorization;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

class CalendarAccess
{
    public function __construct(private WorkspaceAccess $workspaces, private ProjectAccess $projects) {}

    /** Does the user hold $permission for calendar data in this workspace/project scope? */
    public function scope(User $user, Workspace $workspace, ?Project $project, string $permission = 'calendar.view'): bool
    {
        if (! $this->workspaces->allows($user, $workspace, 'workspace.view')) {
            return false;
        }

        return $project ? $project->workspace_id === $workspace->id && $this->projects->allows($user, $project, 'calendar.view') && $this->projects->allows($user, $project, $permission) : $this->workspaces->allows($user, $workspace, 'calendar.view') && $this->workspaces->allows($user, $workspace, $permission);
    }
}
