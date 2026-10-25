<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\Project;
use App\Models\SavedTaskFilter;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Services\TaskService;
use App\Services\TaskUtilityService;
use App\Services\TaskEditConflict;
use App\Services\TaskTreeService;
use App\Services\TaskWorkflowService;
use App\Support\ProjectAccess;
use App\Support\SearchTerm;
use App\Support\TaskFilters;
use App\Support\UiLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function __construct(private TaskService $service, private TaskPolicy $policy, private ProjectAccess $access) {}

    private function context(Request $request, Workspace $workspace, ?Project $project): array
    {
        abort_unless($this->policy->scope($request->user(), $workspace, $project, 'task.view'), 403);

        return ['deadlinePresets' => app(TaskService::class)->presets($workspace), 'workspace' => $workspace, 'project' => $project, 'routePrefix' => $project ? 'workspaces.projects.tasks' : 'workspaces.tasks', 'routeParams' => $project ? [$workspace, $project] : [$workspace],
            'canCreate' => $this->policy->projectWritable($project) && $this->policy->scope($request->user(), $workspace, $project, 'task.create'),
            'parents' => $workspace->tasks()->where('project_id', $project?->id)->where('task_type', 'GROUP')->whereNull('archived_at')->orderBy('sort_order')->get(),
            'people' => $this->access->eligibleMembers($workspace, $project), 'reviewers' => $this->access->eligibleMembers($workspace, $project, 'task.review'),
            'canAssign' => $this->policy->scope($request->user(), $workspace, $project, 'task.assign')];
    }

    private function query(Workspace $workspace, ?Project $project)
    {
        return $workspace->tasks()->where('project_id', $project?->id);
    }

    public function index(Request $request, Workspace $workspace, ?Project $project = null)
    {
        $context = $this->context($request, $workspace, $project);
        $filters = TaskFilters::clean($request->query());
        $query = $this->query($workspace, $project)->with('assignee', 'reviewer');
        $archive = $filters['archived'] ?? 'active';
        if ($archive !== 'all') {
            $archive === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        }
        $query->when(isset($filters['q']) && $filters['q'] !== '', fn ($q) => $q->where(fn ($s) => $s->whereRaw("title LIKE ? ESCAPE '!'", [SearchTerm::contains($filters['q'])])->orWhereRaw("code LIKE ? ESCAPE '!'", [SearchTerm::contains($filters['q'])])));
        foreach (['status', 'priority', 'assignee_id'] as $field) {
            $query->when($filters[$field] ?? null, fn ($q, $v) => $q->where($field, $v));
        }
        TaskFilters::applyPreset($query, $filters, $request->user(), $workspace, $project);
        $direction = $filters['direction'] ?? 'desc';
        $sort = $filters['sort'] ?? 'created_at';
        if ($sort === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'urgent' THEN 4 ELSE 0 END ".$direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        if (($filters['view'] ?? 'list') === 'board') {
            $board = [];
            foreach (TaskStatus::cases() as $status) {
                $board[$status->value] = (clone $query)->where('task_type', '!=', 'GROUP')->where('status', $status->value)->orderBy('id')->paginate(10, ['*'], 'page_'.strtolower($status->value))->withQueryString();
            }

            return view('tasks.board', $context + compact('board', 'filters'));
        }
        $tasks = $query->orderBy('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();

        return view('tasks.index', $context + compact('tasks', 'filters') + ['people' => $this->access->eligibleMembers($workspace, $project), 'savedFilters' => SavedTaskFilter::where('user_id', $request->user()->id)->where('workspace_id', $workspace->id)->where('project_id', $project?->id)->orderBy('name')->get()]);
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

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã tạo công việc mới thành công.',
                'task' => $task->load('assignee', 'reviewer'),
                'redirect' => route($context['routePrefix'].'.show', [...$context['routeParams'], $task]),
            ], 201);
        }

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
        $historyPeople = User::whereIn('id', $workspace->members()->pluck('user_id'))->pluck('name', 'id')->all();
        $historyTasks = $all->pluck('title', 'id')->all();
        $activityHuman = function ($field, $value) use ($historyPeople, $historyTasks) {
            if (in_array($field, ['assignee_id', 'reviewer_id', 'creator_id'], true)) {
                return $value ? ($historyPeople[$value] ?? 'Không còn trong workspace') : 'Chưa phân công';
            }
            if (in_array($field, ['parent_task_id', 'predecessor_id', 'predecessor_task_id'], true)) {
                return $value ? ($historyTasks[$value] ?? 'Không còn trong phạm vi') : 'Không có';
            }
            if ($value === null) {
                return 'Chưa có';
            }

            return is_scalar($value) ? (in_array($field, ['status', 'priority', 'task_type'], true) ? UiLabels::text($value) : (string) $value) : json_encode($value, JSON_UNESCAPED_UNICODE);
        };
        $dependencies = $task->incomingDependencies()->with('predecessor')->get();
        $blockers = $task->blockers()->with('reporter', 'resolver')->orderByDesc('created_at')->get();
        $candidates = $all->filter(fn ($t) => $t->id !== $task->id && $t->task_type->value !== 'GROUP' && ! $t->archived_at);
        $progress = app(TaskTreeService::class)->progress(app(TaskTreeService::class)->descendants($task, $all));
        $deletable = $task->status->value === 'TODO' && ! $task->started_at && ! $task->completed_at && ! $task->activities()->withTrashed()->exists();
        $deadlineInsight = app(TaskService::class)->deadlineInsight($task);
        $workflowUi = app(TaskWorkflowService::class)->availability($request->user(), $task);
        $statusOptions = app(TaskWorkflowService::class)->options($request->user(), $task);

        return view('tasks.show', $this->context($request, $workspace, $project) + compact('deadlineInsight', 'activityHuman', 'task', 'activities', 'dependencies', 'blockers', 'candidates', 'progress', 'statusOptions', 'workflowUi', 'deletable') + app(TaskUtilityService::class)->viewData($task));
    }

    public function edit(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        if ($request->boolean('fresh')) {
            $request->session()->forget('_old_input');

            return redirect()->to($request->fullUrlWithoutQuery(['fresh']));
        }
        $this->bound($workspace, $project, $task);
        $this->authorize('update', $task);

        return view('tasks.edit', $this->context($request, $workspace, $project) + compact('task') + ['people' => $this->access->eligibleMembers($workspace, $project)]);
    }

    public function update(TaskRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);

        return $this->saveEdit($request, $workspace, $project, $task, $request->validated(), $request->input('edit_surface') === 'quick' ? 'quick' : 'full');
    }

    private function saveEdit(Request $request, Workspace $workspace, ?Project $project, Task $task, array $data, string $surface, ?string $token = null)
    {
        try {
            $this->service->save($request->user(), $workspace, $project, $data, $task);
        } catch (ValidationException $e) {
            $task = $task->fresh();
            $this->authorize('update', $task);
            if (isset($e->errors()['expected_revision'])) {
                $conflict = app(TaskEditConflict::class)->prepare($request, $task, $data, $surface);
                if ($token) {
                    app(TaskEditConflict::class)->forget($request, $token);
                }

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'conflict' => true,
                        'message' => 'Công việc đã được cập nhật bởi một phiên làm việc khác.',
                    ], 409);
                }

                return response()->view('tasks.conflict', $this->context($request, $workspace, $project) + compact('task', 'conflict'), 409);
            }
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.',
                    'errors' => $e->errors(),
                ], 422);
            }
            $request->session()->flash('task_edit_error', ['task_id' => $task->id, 'surface' => $surface]);
            $context = $this->context($request, $workspace, $project);

            return to_route($context['routePrefix'].($surface === 'quick' ? '.show' : '.edit'), [...$context['routeParams'], $task])->withErrors($e->errors())->withInput($data + ['edit_surface' => $surface]);
        }
        if ($token) {
            app(TaskEditConflict::class)->forget($request, $token);
        }
        if ($request->wantsJson() || $request->ajax()) {
            $task = $task->fresh(['assignee', 'reviewer']);

            return response()->json([
                'success' => true,
                'message' => 'Đã lưu cập nhật công việc thành công.',
                'task' => [
                    'id' => $task->id,
                    'code' => $task->code,
                    'title' => $task->title,
                    'status' => $task->status->value,
                    'status_label' => UiLabels::text($task->status),
                    'priority' => $task->priority->value,
                    'priority_label' => UiLabels::text($task->priority),
                    'task_type' => $task->task_type->value,
                    'task_type_label' => UiLabels::text($task->task_type),
                    'assignee_id' => $task->assignee_id,
                    'assignee_name' => $task->assignee?->name ?? 'Chưa phân công',
                    'reviewer_id' => $task->reviewer_id,
                    'reviewer_name' => $task->reviewer?->name ?? 'Chưa phân công',
                    'deadline_formatted' => $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') : null,
                    'deadline_at' => $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') : '',
                    'estimate_minutes' => $task->estimate_minutes,
                    'revision' => $task->revision,
                ],
            ]);
        }
        $context = $this->context($request, $workspace, $project);

        return to_route($context['routePrefix'].'.show', [...$context['routeParams'], $task])->with('success', 'Đã cập nhật task.');
    }

    public function resolveEdit(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->authorize('update', $task);
        try {
            $input = $request->validate(['conflict_token' => 'required|uuid', 'expected_revision' => 'required|integer|min:1', 'choices' => 'sometimes|array', 'choices.*' => 'required|in:mine,server', 'deadline_reason' => 'nullable|string|max:2000']);
            $resolved = app(TaskEditConflict::class)->resolve($request, $task, $input);
        } catch (ValidationException $e) {
            return response()->view('tasks.conflict-unavailable', $this->context($request, $workspace, $project) + ['task' => $task, 'messages' => $e->errors()], 422);
        }
        try {
            $data = Validator::make($resolved['data'], TaskRequest::fieldRules($workspace, $task))->validate();
        } catch (ValidationException $e) {
            $request->session()->flash('task_edit_error', ['task_id' => $task->id, 'surface' => $resolved['surface']]);
            $context = $this->context($request, $workspace, $project);

            return to_route($context['routePrefix'].($resolved['surface'] === 'quick' ? '.show' : '.edit'), [...$context['routeParams'], $task])->withErrors($e->errors())->withInput($resolved['data'] + ['edit_surface' => $resolved['surface']]);
        }

        return $this->saveEdit($request, $workspace, $project, $task, $data, $resolved['surface'], $input['conflict_token']);
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
