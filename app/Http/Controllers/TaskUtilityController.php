<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\SavedTaskFilter;
use App\Models\Workspace;
use App\Services\TaskUtilityService;
use App\Support\TaskFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaskUtilityController extends Controller
{
    public function store(Request $request, Workspace $workspace, ?Project $project = null)
    {
        TaskUtilityService::checkScope($request->user(), $workspace, $project);
        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'query' => 'required|array',
        ]);
        app(TaskUtilityService::class)->saveFilter($request->user(), $workspace, $project, $validated['name'], $validated['query']);

        return redirect(TaskUtilityService::indexUrl($workspace, $project, TaskFilters::clean($validated['query'])))->with('success', 'Đã lưu bộ lọc riêng của bạn.');
    }

    private function bound(Request $request, Workspace $workspace, ?Project $project, SavedTaskFilter $saved): void
    {
        TaskUtilityService::checkScope($request->user(), $workspace, $project);
        abort_unless($saved->user_id === $request->user()->id && $saved->workspace_id === $workspace->id && $saved->project_id === $project?->id, 404);
    }

    public function open(Request $request, Workspace $workspace, ?Project $project = null, ?SavedTaskFilter $saved = null)
    {
        $this->bound($request, $workspace, $project, $saved);

        return redirect(TaskUtilityService::indexUrl($workspace, $project, TaskFilters::clean($saved->query)));
    }

    public function destroy(Request $request, Workspace $workspace, ?Project $project = null, ?SavedTaskFilter $saved = null)
    {
        $this->bound($request, $workspace, $project, $saved);
        $saved->delete();

        return redirect(TaskUtilityService::indexUrl($workspace, $project))->with('success', 'Đã xóa bộ lọc cá nhân.');
    }

    public function bulk(Request $request, Workspace $workspace, ?Project $project = null)
    {
        TaskUtilityService::checkScope($request->user(), $workspace, $project);
        $validated = $request->validate([
            'action' => 'required|in:priority,assign,archive',
            'items' => 'required|array|min:1|max:50',
            'items.*' => 'required|integer|min:1',
            'priority' => ['required_if:action,priority', 'nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => 'nullable|uuid',
        ]);
        foreach (array_keys($validated['items']) as $id) {
            abort_unless(Str::isUuid($id), 422);
        }

        $count = app(TaskUtilityService::class)->bulk($request->user(), $workspace, $project, $validated);

        return redirect(TaskUtilityService::indexUrl($workspace, $project))->with('success', 'Đã xử lý '.$count.' công việc. Đã lưu đồng thời cả nhóm.');
    }
}
