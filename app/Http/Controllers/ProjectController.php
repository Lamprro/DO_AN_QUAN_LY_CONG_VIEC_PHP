<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\ProjectService;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(private ProjectAccess $access, private ProjectService $service) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);
        $filters = $request->validate(['view' => ['nullable', Rule::in(['cards', 'table'])], 'q' => ['nullable', 'string', 'max:180'], 'status' => ['nullable', Rule::enum(ProjectStatus::class)], 'archived' => ['nullable', Rule::in(['active', 'archived', 'all'])], 'sort' => ['nullable', Rule::in(['name', 'created_at', 'deadline'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $query = $this->access->visible($request->user(), $workspace)->withCount('tasks');
        $archive = $filters['archived'] ?? 'active';
        if ($archive !== 'all') {
            $archive === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        }
        $projects = $query->when(isset($filters['q']) && $filters['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%'))->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->orderBy('id')->paginate(12)->withQueryString();

        foreach ($projects as $listed) {
            if (! $this->access->allows($request->user(), $listed, 'task.view')) {
                $listed->setAttribute('tasks_count', null);
            }
        }

        return view('projects.index', compact('workspace', 'projects', 'filters') + ['canCreate' => $this->access->canCreate($request->user(), $workspace)]);
    }

    private function form(Request $request, Workspace $workspace, ?Project $project = null): array
    {
        $parents = $this->access->visible($request->user(), $workspace)->whereNull('archived_at')->when($project, fn ($q) => $q->whereKeyNot($project->id))->orderBy('name')->get();

        return compact('workspace', 'project', 'parents');
    }

    public function create(Request $request, Workspace $workspace)
    {
        abort_unless($this->access->canCreate($request->user(), $workspace), 403);

        return view('projects.create', $this->form($request, $workspace));
    }

    public function store(ProjectRequest $request, Workspace $workspace)
    {
        $project = $this->service->save($request->user(), $workspace, $request->validated());

        return to_route('workspaces.projects.show', [$workspace, $project])->with('success', 'Đã tạo project và membership của người tạo.');
    }

    public function show(Request $request, Workspace $workspace, Project $project)
    {
        $this->authorize('view', $project);
        $project->load('creator')->loadCount('members', 'tasks');
        if (! $this->access->allows($request->user(), $project, 'task.view')) {
            $project->setAttribute('tasks_count', null);
        }
        $parent = $project->parent && $request->user()->can('view', $project->parent) ? $project->parent : null;
        $children = $this->access->visible($request->user(), $workspace)->where('parent_project_id', $project->id)->get();

        return view('projects.show', compact('workspace', 'project', 'parent', 'children') + ['canViewTasks' => $this->access->allows($request->user(), $project, 'task.view')]);
    }

    public function edit(Request $request, Workspace $workspace, Project $project)
    {
        if ($request->boolean('fresh')) {
            $request->session()->forget('_old_input');
        }
        $this->authorize('update', $project);

        return view('projects.edit', $this->form($request, $workspace, $project));
    }

    public function update(ProjectRequest $request, Workspace $workspace, Project $project)
    {
        $this->service->save($request->user(), $workspace, $request->validated(), $project);

        return to_route('workspaces.projects.show', [$workspace, $project])->with('success', 'Đã cập nhật project.');
    }

    public function archive(Request $request, Workspace $workspace, Project $project)
    {
        $this->service->archive($request->user(), $workspace, $project);

        return back()->with('success', 'Đã lưu trữ project.');
    }

    public function restore(Request $request, Workspace $workspace, Project $project)
    {
        $this->service->archive($request->user(), $workspace, $project, true);

        return back()->with('success', 'Đã khôi phục project.');
    }

    public function destroy(Request $request, Workspace $workspace, Project $project)
    {
        $this->service->delete($request->user(), $workspace, $project);

        return to_route('workspaces.projects.index', $workspace)->with('success', 'Đã xóa project nháp trống.');
    }
}
