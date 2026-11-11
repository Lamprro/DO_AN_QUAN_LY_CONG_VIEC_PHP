<?php

namespace App\Services\Task;

use App\Authorization\ProjectAccess;
use App\Authorization\TaskAccess;
use App\Authorization\WorkspaceAccess;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Read-only listing scope. Single-task routes and writes always keep their source scope. */
class TaskListingQuery
{
    public function __construct(private TaskAccess $tasks, private WorkspaceAccess $workspaces, private ProjectAccess $projects) {}

    public function isPersonalOverview(User $actor, Workspace $workspace, ?Project $project): bool
    {
        return ! $project && $workspace->kind->value === 'personal' && $workspace->owner_id === $actor->id;
    }

    public function query(User $actor, Workspace $workspace, ?Project $project): Builder
    {
        abort_unless($this->tasks->scope($actor, $workspace, $project, 'task.view'), 403);
        $query = Task::query()->with('workspace', 'project', 'assignee', 'reviewer');
        if (! $this->isPersonalOverview($actor, $workspace, $project)) {
            return $query->where('workspace_id', $workspace->id)->where('project_id', $project?->id);
        }

        $projectIds = $this->accessibleProjects($actor)->pluck('id');

        return $query->where(fn ($scope) => $scope
            ->where(fn ($own) => $own->where('workspace_id', $workspace->id)->whereNull('project_id'))
            ->orWhere(fn ($assigned) => $assigned->whereIn('project_id', $projectIds)
                ->where('assignee_id', $actor->id)->where('task_type', '!=', 'GROUP')));
    }

    /** Apply review rights from each source, never from the personal workspace's Owner role. */
    public function awaitingReview(Builder $query, User $actor, Workspace $workspace): void
    {
        $projectIds = $this->accessibleProjects($actor)->filter(fn (Project $project) => $this->tasks->projectWritable($project) && $this->projects->allows($actor, $project, 'task.review')
        )->pluck('id');
        $query->whereNull('archived_at')->where('status', 'REVIEW')->where('task_type', '!=', 'GROUP')
            ->where(fn ($scope) => $scope->whereIn('project_id', $projectIds)
                ->orWhere(fn ($own) => $own->where('workspace_id', $workspace->id)->whereNull('project_id')))
            ->where(fn ($reviewer) => $reviewer->where('reviewer_id', $actor->id)
                ->orWhereHas('workspace', fn ($source) => $source->where('owner_id', $actor->id)));
    }

    private function accessibleProjects(User $actor): Collection
    {
        return $this->workspaces->visible($actor)->flatMap(fn (Workspace $workspace) => $this->projects->visible($actor, $workspace)->get()
            ->filter(fn (Project $project) => $this->tasks->scope($actor, $workspace, $project, 'task.view'))
        );
    }
}
