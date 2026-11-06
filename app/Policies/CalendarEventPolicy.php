<?php

namespace App\Policies;

use App\Authorization\CalendarAccess;
use App\Authorization\TaskAccess;
use App\Authorization\WorkspaceAccess;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Abilities on a single calendar event. Scope rules live in App\Authorization\CalendarAccess. */
class CalendarEventPolicy
{
    public function __construct(private CalendarAccess $calendars, private WorkspaceAccess $workspaces, private TaskAccess $tasks) {}

    public function view(User $user, CalendarEvent $event): bool
    {
        if (! $event->workspace || ! $this->calendars->scope($user, $event->workspace, $event->project, 'calendar.view')) {
            return false;
        }
        if (! $event->user || ! $this->workspaces->allows($event->user, $event->workspace, 'workspace.view')) {
            return false;
        }
        if ($event->task && ! Gate::forUser($user)->allows('view', $event->task)) {
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
            && $this->calendars->scope($user, $event->workspace, $event->project, $permission);
    }
}
