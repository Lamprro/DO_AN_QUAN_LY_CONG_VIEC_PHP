<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Services\TaskService;
use App\Services\TaskTreeService;
use App\Services\TaskWorkflowService;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private TaskService $service, private TaskPolicy $policy, private ProjectAccess $access) {}

    private function context(Request $request, Workspace $workspace, ?Project $project): array
    {
        abort_unless($this->policy->scope($request->user(), $workspace, $project, 'task.view'), 403);

        return ['workspace' => $workspace, 'project' => $project, 'routePrefix' => $project ? 'workspaces.projects.tasks' : 'workspaces.tasks', 'routeParams' => $project ? [$workspace, $project] : [$workspace],
            'canCreate' => $this->policy->projectWritable($project) && $this->policy->scope($request->user(), $workspace, $project, 'task.create'),
            'parents' => $workspace->tasks()->where('project_id', $project?->id)->where('task_type', 'GROUP')->whereNull('archived_at')->orderBy('sort_order')->get(),
            'reviewers' => $this->access->eligibleMembers($workspace, $project, 'task.review'),
            'canAssign' => $this->policy->scope($request->user(), $workspace, $project, 'task.assign')];
    }

    private function query(Workspace $workspace, ?Project $project)
    {
        return $workspace->tasks()->where('project_id', $project?->id);
    }

    public function index(Request $request, Workspace $workspace, ?Project $project = null)
    {
        $context = $this->context($request, $workspace, $project);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', Rule::enum(TaskStatus::class)], 'priority' => ['nullable', Rule::enum(TaskPriority::class)], 'assignee_id' => ['nullable', 'uuid'], 'archived' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'sort' => ['nullable', Rule::in(['code', 'title', 'priority', 'deadline_at', 'created_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', Rule::in([10, 25, 50])]]);
        $query = $this->query($workspace, $project)->with('assignee', 'reviewer');
        $archive = $filters['archived'] ?? 'active';
        if ($archive !== 'all') {
            $archive === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        }
        $query->when(isset($filters['q']) && $filters['q'] !== '', fn ($q) => $q->where(fn ($s) => $s->where('title', 'like', '%'.$filters['q'].'%')->orWhere('code', 'like', '%'.$filters['q'].'%')));
        foreach (['status', 'priority', 'assignee_id'] as $field) {
            $query->when($filters[$field] ?? null, fn ($q, $v) => $q->where($field, $v));
        }
        $direction = $filters['direction'] ?? 'desc';
        $sort = $filters['sort'] ?? 'created_at';
        if ($sort === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'urgent' THEN 4 ELSE 0 END ".$direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        $tasks = $query->orderBy('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();

        return view('tasks.index', $context + compact('tasks', 'filters') + ['people' => $this->access->eligibleMembers($workspace, $project)]);
    }

    public function create(Request $request, Workspace $workspace, ?Project $project = null)
    {
        $context = $this->context($request, $workspace, $project);
        abort_unless($context['canCreate'], 403);

        return view('tasks.create', $context + ['task' => null, 'people' => $this->access->eligibleMembers($workspace, $project)]);
    }

    public function store(TaskRequest $request, Workspace $workspace, ?Project $project = null)
    {
        $context = $this->context($request, $workspace, $project);
        $task = $this->service->save($request->user(), $workspace, $project, $request->validated());

        return to_route($context['routePrefix'].'.show', [...$context['routeParams'], $task])->with('success', 'Đã tạo công việc.');
    }

    private function bound(Workspace $workspace, ?Project $project, Task $task): void
    {
        abort_unless($task->workspace_id === $workspace->id && $task->project_id === $project?->id, 404);
    }

    public function show(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->authorize('view', $task);
        $task->load('assignee', 'reviewer', 'creator');

        $all = $workspace->tasks()->where('project_id', $project?->id)->get();
        $activityType = $request->validate(['activity_type' => ['nullable', Rule::in(['COMMENT', 'HISTORY', 'ATTACHMENT'])]])['activity_type'] ?? null;
        $activities = $task->activities()->with('actor')->when($activityType, fn ($q) => $q->where('type', $activityType))->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();
        $dependencies = $task->incomingDependencies()->with('predecessor')->get();
        $blockers = $task->blockers()->with('reporter', 'resolver')->orderByDesc('created_at')->get();
        $candidates = $all->filter(fn ($t) => $t->id !== $task->id && $t->task_type->value !== 'GROUP' && ! $t->archived_at);
        $progress = app(TaskTreeService::class)->progress(app(TaskTreeService::class)->descendants($task, $all));
        $deletable = $task->status->value === 'TODO' && ! $task->started_at && ! $task->completed_at && ! $task->activities()->withTrashed()->exists();
        $statusOptions = app(TaskWorkflowService::class)->options($request->user(), $task);

        return view('tasks.show', $this->context($request, $workspace, $project) + compact('task', 'activities', 'dependencies', 'blockers', 'candidates', 'progress', 'statusOptions', 'deletable'));
    }

    public function edit(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->authorize('update', $task);

        return view('tasks.edit', $this->context($request, $workspace, $project) + compact('task') + ['people' => $this->access->eligibleMembers($workspace, $project)]);
    }

    public function update(TaskRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->service->save($request->user(), $workspace, $project, $request->validated(), $task);
        $context = $this->context($request, $workspace, $project);

        return to_route($context['routePrefix'].'.show', [...$context['routeParams'], $task])->with('success', 'Đã cập nhật task.');
    }

    public function archive(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->service->archive($request->user(), $workspace, $project, $task);

        return back()->with('success', 'Đã lưu trữ task.');
    }

    public function restore(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->service->archive($request->user(), $workspace, $project, $task, true);

        return back()->with('success', 'Đã khôi phục task.');
    }

    public function destroy(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $context = $this->context($request, $workspace, $project);
        $this->service->delete($request->user(), $workspace, $project, $task);

        return to_route($context['routePrefix'].'.index', $context['routeParams'])->with('success', 'Đã xóa task nháp chưa có dữ liệu liên quan.');
    }
}
