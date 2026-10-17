<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ProjectAccess;
use App\Support\WorkspaceAccess;

class CalendarEventPolicy
{
    public function __construct(private WorkspaceAccess $workspaces, private ProjectAccess $projects, private TaskPolicy $tasks) {}

    public function scope(User $user, Workspace $workspace, ?Project $project, string $permission): bool
    {
        if (! $this->workspaces->allows($user, $workspace, 'workspace.view')) {
            return false;
        }

        return $project ? $project->workspace_id === $workspace->id && $this->projects->allows($user, $project, 'calendar.view') && $this->projects->allows($user, $project, $permission)
            : $this->workspaces->allows($user, $workspace, 'calendar.view') && $this->workspaces->allows($user, $workspace, $permission);
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        if (! $event->workspace || ! $this->scope($user, $event->workspace, $event->project, 'calendar.view')) {
            return false;
        }
        if (! $event->user || ! $this->workspaces->allows($event->user, $event->workspace, 'workspace.view')) {
            return false;
        }
        if ($event->task && ! $this->tasks->view($user, $event->task)) {
            return false;
        }
        if ($event->user_id === $user->id) {
            return true;
        }

        return $event->visibility->value === 'workspace' || ($event->visibility->value === 'project' && $event->project_id !== null);
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $this->write($user, $event, 'calendar.update');
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $this->write($user, $event, 'calendar.delete');
    }

    private function write(User $user, CalendarEvent $event, string $permission): bool
    {
        return $event->user_id === $user->id && $event->source === 'manual' && ! $event->plan_id && ! $event->integration_connection_id
            && $this->view($user, $event) && $this->tasks->projectWritable($event->project)
            && $this->scope($user, $event->workspace, $event->project, $permission);
    }
}
