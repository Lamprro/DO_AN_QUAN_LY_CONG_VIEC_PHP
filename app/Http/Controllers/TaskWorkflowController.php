<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskActionRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Services\TaskUtilityService;
use App\Services\TaskTreeService;
use App\Services\TaskWorkflowService;
use App\Support\UiLabels;
use Illuminate\Http\Request;

class TaskWorkflowController extends Controller
{
    public function __construct(private TaskWorkflowService $workflow, private TaskUtilityService $files, private TaskTreeService $trees, private TaskPolicy $policy) {}

    private function bound(Workspace $workspace, ?Project $project, Task $task): void
    {
        abort_unless($task->workspace_id === $workspace->id && $task->project_id === $project?->id, 404);
    }

    public function tree(Request $request, Workspace $workspace, ?Project $project = null)
    {
        abort_unless($this->policy->scope($request->user(), $workspace, $project, 'task.view'), 403);
        $tasks = $workspace->tasks()->where('project_id', $project?->id)->with('assignee')->orderBy('sort_order')->orderBy('id')->get();
        $nodes = $tasks->groupBy(fn ($t) => $t->parent_task_id ?? 'root');
        $progress = $this->trees->progress($tasks);

        return view('tasks.tree', compact('workspace', 'project', 'tasks', 'nodes', 'progress') + ['routePrefix' => $project ? 'workspaces.projects.tasks' : 'workspaces.tasks', 'routeParams' => $project ? [$workspace, $project] : [$workspace]]);
    }

    public function status(TaskActionRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->workflow->status($request->user(), $task, $request->validated());

        if ($request->wantsJson() || $request->ajax()) {
            $task = $task->fresh(['assignee', 'reviewer']);

            return response()->json([
                'success' => true,
                'message' => 'Đã chuyển trạng thái sang '.UiLabels::text($task->status),
                'status' => $task->status->value,
                'status_label' => UiLabels::text($task->status),
                'task' => $task,
            ]);
        }

        return back()->with('success', 'Đã xử lý trạng thái công việc.');
    }

    public function dependency(TaskActionRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->workflow->dependency($request->user(), $task, $request->validated());

        return back()->with('success', 'Đã thêm dependency FS.');
    }

    public function removeDependency(TaskActionRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->workflow->removeDependency($request->user(), $task, $request->route('dependency'));

        return back()->with('success', 'Đã gỡ dependency.');
    }

    public function blocker(TaskActionRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->workflow->blocker($request->user(), $task, $request->validated(), $request->route('blocker'));

        return back()->with('success', 'Đã xử lý blocker.');
    }

    public function comment(TaskActionRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->workflow->comment($request->user(), $task, $request->validated(), $request->route('activity'), $request->isMethod('DELETE'));

        return back()->with('success', 'Đã xử lý bình luận.');
    }

    public function upload(TaskActionRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->files->storeAttachment($request->user(), $task, $request->file('file'));

        return back()->with('success', 'Đã tải tệp lên.');
    }

    public function download(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);

        return $this->files->downloadAttachment($request->user(), $task, $request->route('activity'));
    }

    public function removeFile(TaskActionRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->bound($workspace, $project, $task);
        $this->files->removeAttachment($request->user(), $task, $request->route('activity'));

        return back()->with('success', 'Đã gỡ tệp đính kèm.');
    }
}
