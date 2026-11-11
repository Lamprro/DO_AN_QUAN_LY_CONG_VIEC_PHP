<?php

namespace App\Services\Search;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspaceAccess;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\SearchTerm;

/** Global search over tasks and projects the user is allowed to see. */
class SearchService
{
    public function __construct(private WorkspaceAccess $workspaces, private ProjectAccess $projects) {}

    public function search(User $actor, array $filters): array
    {
        $workspaces = $this->workspaces->visible($actor);
        if (! empty($filters['workspace_id'])) {
            abort_unless($workspaces->contains('id', $filters['workspace_id']), 403);
        }
        $scope = $workspaces->when(! empty($filters['workspace_id']), fn ($c) => $c->where('id', $filters['workspace_id']));
        $projectIds = [];
        $taskProjectIds = [];
        $personal = [];
        foreach ($scope as $workspace) {
            foreach ($this->projects->visible($actor, $workspace)->get() as $project) {
                $projectIds[] = $project->id;
                if ($this->projects->allows($actor, $project, 'task.view')) {
                    $taskProjectIds[] = $project->id;
                }
            }
            if ($workspace->kind->value === 'personal' && $workspace->owner_id === $actor->id && $this->workspaces->allows($actor, $workspace, 'task.view')) {
                $personal[] = $workspace->id;
            }
        }
        $term = trim($filters['q'] ?? '');
        $pattern = SearchTerm::contains($term);
        $type = $filters['type'] ?? 'all';
        $tasks = Task::query()
            ->whereIn('workspace_id', $scope->pluck('id'))
            ->where(fn ($q) => $q->whereIn('project_id', $taskProjectIds)->orWhere(fn ($q) => $q->whereNull('project_id')->whereIn('workspace_id', $personal)))
            ->whereNull('archived_at')
            ->when($term === '' || $type === 'projects', fn ($q) => $q->whereRaw('1=0'))
            ->where(fn ($q) => $q->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("code LIKE ? ESCAPE '!'", [$pattern]))
            ->with('workspace', 'project', 'assignee')
            ->latest()
            ->orderBy('id')
            ->paginate(10, ['*'], 'tasks_page')
            ->withQueryString();
        $projects = Project::query()
            ->whereIn('workspace_id', $scope->pluck('id'))
            ->whereIn('id', $projectIds)
            ->whereNull('archived_at')
            ->when($term === '' || $type === 'tasks', fn ($q) => $q->whereRaw('1=0'))
            ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
            ->with('workspace')
            ->latest()
            ->orderBy('id')
            ->paginate(10, ['*'], 'projects_page')
            ->withQueryString();

        return compact('filters', 'workspaces', 'tasks', 'projects', 'term');
    }
}
