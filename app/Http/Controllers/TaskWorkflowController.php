<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesTaskRoutes;
use App\Http\Requests\Task\TaskAttachmentRequest;
use App\Http\Requests\Task\TaskBlockerRequest;
use App\Http\Requests\Task\TaskCommentRequest;
use App\Http\Requests\Task\TaskDependencyRequest;
use App\Http\Requests\Task\TaskStatusRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\Task\TaskPageData;
use App\Services\Task\TaskWorkflowService;
use App\Support\UiLabels;
use Illuminate\Http\Request;

/** Status transitions, dependencies, blockers, comments and attachments. Every write is authorized in the service. */
class TaskWorkflowController extends Controller
{
    use ScopesTaskRoutes;

    public function __construct(private TaskWorkflowService $workflow, private TaskPageData $pages) {}

    public function tree(Request $request, Workspace $workspace, ?Project $project = null)
    {
        return view('tasks.tree', $this->pages->tree($request->user(), $workspace, $project));
    }

    public function statusForm(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->authorize('view', $task);

        return $request->boolean('panel')
            ? view('tasks.panel', $this->pages->statusPanel($request->user(), $workspace, $project, $task))
            : redirect()->route($this->taskRoute($project, 'show'), $this->taskRouteParams($workspace, $project, $task));
    }

    public function status(TaskStatusRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->workflow->status($request->user(), $task, $request->validated());

        if ($request->wantsJson() || $request->ajax()) {
            $task = $task->fresh(['assignee', 'reviewer']);

            return response()->json([
                'success' => true,
                'message' => 'Đã chuyển trạng thái sang '.UiLabels::text($task->status),
                'status' => $task->status->value,
                'status_label' => UiLabels::text($task->status),
                'task' => ['id' => $task->id, 'revision' => $task->revision],
            ]);
        }

        return back()->with('success', 'Đã xử lý trạng thái công việc.');
    }

    public function dependency(TaskDependencyRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->workflow->dependency($request->user(), $task, $request->validated());

        return back()->with('success', 'Đã thêm dependency FS.');
    }

    public function removeDependency(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->workflow->removeDependency($request->user(), $task, $request->route('dependency'));

        return back()->with('success', 'Đã gỡ dependency.');
    }

    public function blocker(TaskBlockerRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->workflow->blocker($request->user(), $task, $request->validated(), $request->route('blocker'));

        return back()->with('success', 'Đã xử lý blocker.');
    }

    public function comment(TaskCommentRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->workflow->comment($request->user(), $task, $request->validated(), $request->route('activity'));

        return back()->with('success', 'Đã xử lý bình luận.');
    }

    public function deleteComment(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->workflow->comment($request->user(), $task, [], $request->route('activity'), true);

        return back()->with('success', 'Đã xử lý bình luận.');
    }

    public function upload(TaskAttachmentRequest $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->workflow->storeAttachment($request->user(), $task, $request->file('file'));

        return back()->with('success', 'Đã tải tệp lên.');
    }

    public function download(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);

        return $this->workflow->downloadAttachment($request->user(), $task, $request->route('activity'));
    }

    public function removeFile(Request $request, Workspace $workspace, ?Project $project = null, ?Task $task = null)
    {
        $this->assertTaskInScope($workspace, $project, $task);
        $this->workflow->removeAttachment($request->user(), $task, $request->route('activity'));

        return back()->with('success', 'Đã gỡ tệp đính kèm.');
    }

    public function watch(Request $request, Workspace $workspace, string $task, TaskWorkflowService $service)
    {
        $data = $request->validate(['user_id' => 'required|uuid']);
        $taskModel = $workspace->tasks()->findOrFail($task);
        $service->watch($request->user(), $taskModel, $data['user_id']);

        return back()->with('success', 'Đã theo dõi công việc.');
    }

    public function unwatch(Request $request, Workspace $workspace, string $task, string $user, TaskWorkflowService $service)
    {
        $taskModel = $workspace->tasks()->findOrFail($task);
        $service->watch($request->user(), $taskModel, $user, true);

        return back()->with('success', 'Đã dừng theo dõi.');
    }
}
