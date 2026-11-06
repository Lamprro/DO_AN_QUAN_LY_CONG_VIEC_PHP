<?php

namespace App\Services\Project;

use App\Authorization\ProjectAccess;
use App\Authorization\TaskAccess;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SearchTerm;

/** Read model for project screens. */
class ProjectQuery
{
    public function __construct(private ProjectAccess $access, private TaskAccess $tasks) {}

    public function index(User $actor, Workspace $workspace, array $filters): array
    {
        $query = $this->access->visible($actor, $workspace)->withCount('tasks');
        $archive = $filters['archived'] ?? 'active';
        if ($archive !== 'all') {
            $archive === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        }
        $projects = $query->when(isset($filters['q']) && $filters['q'] !== '', fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [SearchTerm::contains($filters['q'])]))->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->orderBy('id')->paginate(12)->withQueryString();
        foreach ($projects as $listed) {
            if (! $this->access->allows($actor, $listed, 'task.view')) {
                $listed->setAttribute('tasks_count', null);
            }
        }

        return compact('workspace', 'projects', 'filters') + ['canCreate' => $this->access->canCreate($actor, $workspace)];
    }

    /** Create/edit form; a new project needs project.create in the workspace. */
    public function form(User $actor, Workspace $workspace, ?Project $project = null): array
    {
        if (! $project) {
            abort_unless($this->access->canCreate($actor, $workspace), 403);
        }
        $parents = $this->access->visible($actor, $workspace)->whereNull('archived_at')->when($project, fn ($q) => $q->whereKeyNot($project->id))->orderBy('name')->get();

        return compact('workspace', 'project', 'parents');
    }

    public function overview(User $actor, Workspace $workspace, Project $project): array
    {
        $project->load('creator')->loadCount('members', 'tasks');
        $canViewTasks = $this->access->allows($actor, $project, 'task.view');
        if (! $canViewTasks) {
            $project->setAttribute('tasks_count', null);
        }

        return [
            'workspace' => $workspace, 'project' => $project,
            'parent' => $project->parent_project_id ? $this->access->visible($actor, $workspace)->whereKey($project->parent_project_id)->first() : null,
            'children' => $this->access->visible($actor, $workspace)->where('parent_project_id', $project->id)->orderBy('name')->get(),
            'canViewTasks' => $canViewTasks,
            'canCreateTask' => $canViewTasks && $this->access->allows($actor, $project, 'task.create') && $this->tasks->projectWritable($project),
            'canReviewTasks' => $this->tasks->scope($actor, $workspace, $project, 'task.review'),
            'recentTasks' => $canViewTasks ? $project->tasks()->with('assignee')->latest('created_at')->limit(10)->get() : collect(),
            'routePrefix' => 'workspaces.projects.tasks',
            'routeParams' => [$workspace, $project],
        ];
    }

    public function members(User $actor, Workspace $workspace, Project $project): array
    {
        $actorPermissions = $this->access->permissions($actor, $project);

        return [
            'workspace' => $workspace, 'project' => $project, 'actorPermissions' => $actorPermissions,
            'roles' => $workspace->roles()->orderBy('name')->get()->filter(fn ($r) => ! ($r->is_system && $r->name === 'Owner') && ! array_diff($r->permissions, $actorPermissions)),
            'members' => $project->members()->with('workspaceMember.user', 'role')->get(),
            'candidates' => $workspace->members()->with('user')->where('status', 'active')->where('user_id', '!=', $actor->id)->where('user_id', '!=', $workspace->owner_id)
                ->whereHas('user', fn ($q) => $q->where('status', 'active'))->whereDoesntHave('projectMemberships', fn ($q) => $q->where('project_id', $project->id)->where('status', '!=', 'left'))->get(),
        ];
    }
}
