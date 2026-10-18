<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\SavedTaskFilter;
use App\Models\Workspace;
use App\Services\TaskUtilityService;
use App\Support\TaskFilters;
use App\Support\TaskUtilityScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaskUtilityController extends Controller
{
    public function store(Request $r, Workspace $workspace, ?Project $project = null)
    {
        TaskUtilityScope::check($r->user(), $workspace, $project);
        $v = $r->validate(['name' => 'required|string|max:80', 'query' => 'required|array']);
        app(TaskUtilityService::class)->saveFilter($r->user(), $workspace, $project, $v['name'], $v['query']);

        return redirect(TaskUtilityScope::url($workspace, $project, TaskFilters::clean($v['query'])))->with('success', 'Đã lưu bộ lọc riêng của bạn.');
    }

    private function bound(Request $r, Workspace $w, ?Project $p, SavedTaskFilter $saved): void
    {
        TaskUtilityScope::check($r->user(), $w, $p);
        abort_unless($saved->user_id === $r->user()->id && $saved->workspace_id === $w->id && $saved->project_id === $p?->id, 404);
    }

    public function open(Request $r, Workspace $workspace, ?Project $project = null, ?SavedTaskFilter $saved = null)
    {
        $this->bound($r, $workspace, $project, $saved);

        return redirect(TaskUtilityScope::url($workspace, $project, TaskFilters::clean($saved->query)));
    }

    public function destroy(Request $r, Workspace $workspace, ?Project $project = null, ?SavedTaskFilter $saved = null)
    {
        $this->bound($r, $workspace, $project, $saved);
        $saved->delete();

        return redirect(TaskUtilityScope::url($workspace, $project))->with('success', 'Đã xóa bộ lọc cá nhân.');
    }

    public function bulk(Request $r, Workspace $workspace, ?Project $project = null)
    {
        TaskUtilityScope::check($r->user(), $workspace, $project);
        $v = $r->validate(['action' => 'required|in:priority,assign,archive', 'items' => 'required|array|min:1|max:50', 'items.*' => 'required|integer|min:1', 'priority' => ['required_if:action,priority', 'nullable', Rule::enum(TaskPriority::class)], 'assignee_id' => 'nullable|uuid']);
        foreach (array_keys($v['items']) as $id) {
            abort_unless(Str::isUuid($id), 422);
        }$count = app(TaskUtilityService::class)->bulk($r->user(), $workspace, $project, $v);

        return redirect(TaskUtilityScope::url($workspace, $project))->with('success', 'Đã xử lý '.$count.' công việc. Đã lưu đồng thời cả nhóm.');
    }
}
