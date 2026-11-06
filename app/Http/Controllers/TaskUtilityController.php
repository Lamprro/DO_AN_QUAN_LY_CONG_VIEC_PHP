<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\BulkTaskRequest;
use App\Http\Requests\Task\SavedFilterRequest;
use App\Models\Project;
use App\Models\SavedTaskFilter;
use App\Models\Workspace;
use App\Services\Task\TaskUtilityService;
use App\Support\TaskFilters;
use Illuminate\Http\Request;

class TaskUtilityController extends Controller
{
    public function __construct(private TaskUtilityService $service) {}

    public function store(SavedFilterRequest $request, Workspace $workspace, ?Project $project = null)
    {
        $validated = $request->validated();
        $this->service->saveFilter($request->user(), $workspace, $project, $validated['name'], $validated['query']);

        return redirect($this->indexUrl($workspace, $project, TaskFilters::clean($validated['query'])))->with('success', 'Đã lưu bộ lọc riêng của bạn.');
    }

    public function open(Request $request, Workspace $workspace, ?Project $project = null, ?SavedTaskFilter $saved = null)
    {
        $saved = $this->service->findFilter($request->user(), $workspace, $project, $saved);

        return redirect($this->indexUrl($workspace, $project, TaskFilters::clean($saved->query)));
    }

    public function destroy(Request $request, Workspace $workspace, ?Project $project = null, ?SavedTaskFilter $saved = null)
    {
        $this->service->deleteFilter($request->user(), $workspace, $project, $saved);

        return redirect($this->indexUrl($workspace, $project))->with('success', 'Đã xóa bộ lọc cá nhân.');
    }

    public function bulk(BulkTaskRequest $request, Workspace $workspace, ?Project $project = null)
    {
        $count = $this->service->bulk($request->user(), $workspace, $project, $request->validated());

        return redirect($this->indexUrl($workspace, $project))->with('success', 'Đã xử lý '.$count.' công việc. Đã lưu đồng thời cả nhóm.');
    }

    private function indexUrl(Workspace $workspace, ?Project $project, array $query = []): string
    {
        return route($project ? 'workspaces.projects.tasks.index' : 'workspaces.tasks.index', array_merge($project ? [$workspace, $project] : [$workspace], $query));
    }
}
