<?php

namespace App\Authorization;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class ReportAccess
{
    public function __construct(private WorkspaceAccess $workspaces, private ProjectAccess $projects, private CalendarAccess $calendars) {}

    public function scope(User $actor, Workspace $workspace, ?Project $project, string $permission = 'report.view'): bool
    {
        if (! $this->workspaces->allows($actor, $workspace, 'workspace.view')) {
            return false;
        }

        return $project ? $project->workspace_id === $workspace->id && $this->projects->allows($actor, $project, 'task.view') && $this->projects->allows($actor, $project, 'report.view') && $this->projects->allows($actor, $project, $permission) : $this->workspaces->allows($actor, $workspace, 'report.view') && $this->workspaces->allows($actor, $workspace, $permission);
    }

    public function projects(User $actor, Workspace $workspace)
    {
        return $this->projects->visible($actor, $workspace)->get()->filter(fn ($project) => $this->scope($actor, $workspace, $project))->values();
    }

    /**
     * Personal workspace owner only: projects (in any workspace) whose tasks assigned to the
     * owner appear in "Công việc cá nhân". The personal report counts the same tasks.
     */
    public function assignedProjects(User $actor, Workspace $workspace): Collection
    {
        if ($workspace->kind->value !== 'personal' || $workspace->owner_id !== $actor->id) {
            return collect();
        }

        return $this->workspaces->visible($actor)
            ->flatMap(fn (Workspace $source) => $this->projects->visible($actor, $source)->get()->filter(fn (Project $project) => $this->projects->allows($actor, $project, 'task.view')))
            ->values();
    }

    public function manifest(User $actor, Workspace $workspace, array $metrics, string $permission = 'report.view'): bool
    {
        if (($metrics['schema_version'] ?? null) !== 1 || ($metrics['workspace_id'] ?? null) !== $workspace->id) {
            return false;
        }
        $project = empty($metrics['filters']['project_id']) ? null : $workspace->projects()->find($metrics['filters']['project_id']);
        if (! empty($metrics['filters']['project_id']) && ! $project) {
            return false;
        }
        if (! $this->scope($actor, $workspace, $project, $permission)) {
            return false;
        }
        $assigned = $this->assignedProjects($actor, $workspace);
        $allowed = $this->projects($actor, $workspace)->pluck('id')->merge($assigned->pluck('id'))->all();
        if (array_diff($metrics['access']['project_ids'] ?? [], $allowed)) {
            return false;
        }
        if (($metrics['access']['personal_tasks'] ?? false) && ($workspace->kind->value !== 'personal' || $workspace->owner_id !== $actor->id || ! $this->workspaces->allows($actor, $workspace, 'task.view'))) {
            return false;
        }
        foreach ($metrics['access']['calendar_project_ids'] ?? [] as $id) {
            $calendarProject = $id ? ($workspace->projects()->find($id) ?? $assigned->firstWhere('id', $id)) : null;
            if ($id && ! $calendarProject || ! $this->calendars->scope($actor, $calendarProject?->workspace ?? $workspace, $calendarProject, 'calendar.view')) {
                return false;
            }
        }

        return true;
    }
}
