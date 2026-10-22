<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Support\ProjectAccess;
use App\Support\SearchTerm;
use App\Support\WorkspaceAccess;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:200', 'workspace_id' => 'nullable|uuid', 'type' => 'nullable|in:all,tasks,projects']);
        $actor = $request->user();
        $access = app(WorkspaceAccess::class);
        $projectAccess = app(ProjectAccess::class);
        $workspaces = Workspace::whereHas('members', fn ($q) => $q->where('user_id', $actor->id)->where('status', 'active'))->orderBy('name')->get()->filter(fn ($w) => $access->allows($actor, $w, 'workspace.view'));
        if (! empty($filters['workspace_id'])) {
            abort_unless($workspaces->contains('id', $filters['workspace_id']), 403);
        }
        $scope = $workspaces->when(! empty($filters['workspace_id']), fn ($c) => $c->where('id', $filters['workspace_id']));
        $projectIds = [];
        $taskProjectIds = [];
        $personal = [];
        foreach ($scope as $w) {
            foreach ($projectAccess->visible($actor, $w)->get() as $p) {
                $projectIds[] = $p->id;
                if ($projectAccess->allows($actor, $p, 'task.view')) {
                    $taskProjectIds[] = $p->id;
                }
            }
            if ($w->kind->value === 'personal' && $w->owner_id === $actor->id && $access->allows($actor, $w, 'task.view')) {
                $personal[] = $w->id;
            }
        }
        $term = trim($filters['q'] ?? '');
        $pattern = SearchTerm::contains($term);
        $type = $filters['type'] ?? 'all';
        $tasks = Task::query()->whereIn('workspace_id', $scope->pluck('id'))->where(fn ($q) => $q->whereIn('project_id', $taskProjectIds)->orWhere(fn ($q) => $q->whereNull('project_id')->whereIn('workspace_id', $personal)))->whereNull('archived_at')->when($term === '' || $type === 'projects', fn ($q) => $q->whereRaw('1=0'))->where(fn ($q) => $q->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("code LIKE ? ESCAPE '!'", [$pattern]))->with('workspace', 'project', 'assignee')->latest()->orderBy('id')->paginate(10, ['*'], 'tasks_page')->withQueryString();
        $projects = Project::query()->whereIn('workspace_id', $scope->pluck('id'))->whereIn('id', $projectIds)->whereNull('archived_at')->when($term === '' || $type === 'tasks', fn ($q) => $q->whereRaw('1=0'))->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->with('workspace')->latest()->orderBy('id')->paginate(10, ['*'], 'projects_page')->withQueryString();

        return view('search.index', compact('filters', 'workspaces', 'tasks', 'projects', 'term'));
    }
}
