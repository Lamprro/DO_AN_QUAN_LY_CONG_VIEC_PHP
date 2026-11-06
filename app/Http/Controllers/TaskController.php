<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesTaskRoutes;
use App\Http\Requests\Task\TaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\Task\TaskEditConflict;
use App\Services\Task\TaskPageData;
use App\Services\Task\TaskService;
use App\Support\TaskFilters;
use App\Support\UiLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    use ScopesTaskRoutes;

    public function __construct(private TaskService $service, private TaskPageData $pages, private TaskEditConflict $conflicts) {}

    public function index(Request $request, Workspace $workspace, ?Project $project = null)
    {
        $filters = TaskFilters::clean($request->query());

        return ($filters['view'] ?? 'list') === 'board'
            ? view('tasks.board', $this->pages->board($request->user(), $workspace, $project, $filters))
            : view('tasks.index', $this->pages->list($request->user(), $workspace, $project, $filters));
    }

    public function create(Request $request, Workspace $workspace, ?Project $project = null)
    {
        $data = $this->pages->createForm($request->user(), $workspace, $project);

        return view($request->boolean('panel') ? 'tasks.panel' : 'tasks.create', $data + ['task' => null, 'panelMode' => 'create']);
    }

    public function store(TaskRequest $request, Workspace $workspace, ?Project $project = null)
    {
        $task = $this->service->save($request->user(), $workspace, $project, $request->validated());
        $url = route($this->taskRoute($project, 'show'), $this->taskRouteParams($workspace, $project, $task));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã tạo công việc mới thành công.',
                'task' => ['id' => $task->id, 'title' => $task->title],
                'redirect' => $url,
            ], 201);
        }

        return redirect($url)->with('success', 'Đã tạo công việc.');
    }

    public function show(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->authorize('view', $task);
        $task->load('assignee', 'reviewer', 'creator');
        if ($request->boolean('panel')) {
            return view('tasks.panel', $this->pages->panel($request->user(), $workspace, $project, $task));
        }
        $activityType = $request->validate(['activity_type' => ['nullable', Rule::in(['COMMENT', 'HISTORY', 'ATTACHMENT'])]])['activity_type'] ?? null;

        return view('tasks.show', $this->pages->show($request->user(), $workspace, $project, $task, $activityType));
    }

    public function edit(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        if ($request->boolean('fresh')) {
            $request->session()->forget('_old_input');

            return redirect()->to($request->fullUrlWithoutQuery(['fresh']));
        }
        $this->assertTaskInScope($workspace, $project, $task);
        $this->authorize('update', $task);

        return view($request->boolean('panel') ? 'tasks.panel' : 'tasks.edit', $this->pages->context($request->user(), $workspace, $project) + compact('task') + ['panelMode' => 'edit']);
    }

    public function update(TaskRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);

        return $this->saveEdit($request, $workspace, $project, $task, $request->validated(), $request->input('edit_surface') === 'quick' ? 'quick' : 'full');
    }

    /** Resolve a stale-revision conflict by picking "mine" or "server" per field, then save. */
    public function resolveEdit(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        // The conflict pages below render the task before any service call, so authorize here.
        $this->authorize('update', $task);
        try {
            $input = $request->validate(['conflict_token' => 'required|uuid', 'expected_revision' => 'required|integer|min:1', 'choices' => 'sometimes|array', 'choices.*' => 'required|in:mine,server', 'deadline_reason' => 'nullable|string|max:2000']);
            $resolved = $this->conflicts->resolve($request, $task, $input);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Bản đối chiếu hết hiệu lực. Mở lại dữ liệu mới và thử lại.', 'errors' => $e->errors()], 422);
            }

            return response()->view('tasks.conflict-unavailable', $this->pages->context($request->user(), $workspace, $project) + ['task' => $task, 'messages' => $e->errors()], 422);
        }
        try {
            $data = Validator::make($resolved['data'], TaskRequest::fieldRules($workspace, $task))->validate();
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Kiểm tra lại lựa chọn.', 'errors' => $e->errors()], 422);
            }
            $request->session()->flash('task_edit_error', ['task_id' => $task->id, 'surface' => $resolved['surface']]);

            return redirect()->route($this->taskRoute($project, $resolved['surface'] === 'quick' ? 'show' : 'edit'), $this->taskRouteParams($workspace, $project, $task))
                ->withErrors($e->errors())->withInput($resolved['data'] + ['edit_surface' => $resolved['surface']]);
        }

        return $this->saveEdit($request, $workspace, $project, $task, $data, $resolved['surface'], $input['conflict_token']);
    }

    public function archive(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->service->archive($request->user(), $workspace, $project, $task);

        return back()->with('success', 'Đã lưu trữ task.');
    }

    public function restore(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->service->archive($request->user(), $workspace, $project, $task, true);

        return back()->with('success', 'Đã khôi phục task.');
    }

    public function destroy(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->service->delete($request->user(), $workspace, $project, $task);

        return redirect()->route($this->taskRoute($project, 'index'), $this->taskRouteParams($workspace, $project))->with('success', 'Đã xóa task nháp chưa có dữ liệu liên quan.');
    }

    private function saveEdit(Request $request, Workspace $workspace, ?Project $project, Task $task, array $data, string $surface, ?string $token = null)
    {
        try {
            $this->service->save($request->user(), $workspace, $project, $data, $task);
        } catch (ValidationException $e) {
            return $this->editFailed($request, $workspace, $project, $task->fresh(), $data, $surface, $token, $e);
        }
        if ($token) {
            $this->conflicts->forget($request, $token);
        }
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Đã lưu cập nhật công việc thành công.', 'task' => $this->taskJson($task->fresh(['assignee', 'reviewer']), $workspace)]);
        }

        return redirect()->route($this->taskRoute($project, 'show'), $this->taskRouteParams($workspace, $project, $task))->with('success', 'Đã cập nhật task.');
    }

    /** TaskService::save already authorized the update; a stale revision shows the conflict screen. */
    private function editFailed(Request $request, Workspace $workspace, ?Project $project, Task $task, array $data, string $surface, ?string $token, ValidationException $e)
    {
        if (isset($e->errors()['expected_revision'])) {
            $conflict = $this->conflicts->prepare($request, $task, $data, $surface);
            if ($token) {
                $this->conflicts->forget($request, $token);
            }
            $page = $this->pages->context($request->user(), $workspace, $project) + compact('task', 'conflict');
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'html' => view('tasks.conflict', $page)->fragment('task-conflict'),
                    'message' => 'Công việc đã được cập nhật bởi một phiên làm việc khác.',
                ], 409);
            }

            return response()->view('tasks.conflict', $page, 409);
        }
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.', 'errors' => $e->errors()], 422);
        }
        $request->session()->flash('task_edit_error', ['task_id' => $task->id, 'surface' => $surface]);

        return redirect()->route($this->taskRoute($project, $surface === 'quick' ? 'show' : 'edit'), $this->taskRouteParams($workspace, $project, $task))
            ->withErrors($e->errors())->withInput($data + ['edit_surface' => $surface]);
    }

    private function taskJson(Task $task, Workspace $workspace): array
    {
        return [
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
        ];
    }
}
