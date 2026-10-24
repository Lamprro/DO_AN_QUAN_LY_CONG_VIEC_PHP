<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateRun;
use App\Models\Workspace;
use App\Services\TaskTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskTemplateController extends Controller
{
    public function index(Request $r, Workspace $workspace, TaskTemplateService $service)
    {
        $d = $r->validate(['project_id' => 'nullable|uuid']);
        $project = empty($d['project_id']) ? null : $workspace->projects()->findOrFail($d['project_id']);
        abort_unless($service->allowed($r->user(), $workspace, $project), 403);
        $templates = TaskTemplate::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->latest()->paginate(15)->withQueryString();
        $sources = $workspace->tasks()->where('project_id', $project?->id)->whereNull('archived_at')->orderBy('sort_order')->get();

        return view('templates.index', compact('workspace', 'project', 'templates', 'sources') + ['canManage' => $service->allowed($r->user(), $workspace, $project, true)]);
    }

    public function store(Request $r, Workspace $workspace, TaskTemplateService $service)
    {
        $d = $r->validate(['project_id' => 'nullable|uuid']);
        $project = empty($d['project_id']) ? null : $workspace->projects()->findOrFail($d['project_id']);
        $t = $service->create($r->user(), $workspace, $project, $r->all());

        return to_route('workspaces.templates.show', [$workspace, $t->id])->with('success', 'Đã lưu mẫu độc lập. Chưa tạo task mới.');
    }

    private function bound(Workspace $workspace, string $template): TaskTemplate
    {
        return TaskTemplate::where('workspace_id', $workspace->id)->findOrFail($template);
    }

    public function show(Request $r, Workspace $workspace, string $template, TaskTemplateService $service)
    {
        $template = $this->bound($workspace, $template);
        abort_unless($service->allowed($r->user(), $workspace, $template->project), 403);
        $runs = TaskTemplateRun::where('task_template_id', $template->id)->latest()->paginate(15);
        $r->attributes->set('scope_project', $template->project);
        $generatedTasks = Task::where('workspace_id', $workspace->id)->where('project_id', $template->project_id)->whereIn('id', $runs->getCollection()->flatMap(fn ($run) => array_values($run->task_map ?? [])))->get(['id', 'code', 'title'])->keyBy('id');

        return view('templates.show', compact('workspace', 'template', 'runs', 'generatedTasks') + ['requestKey' => (string) Str::uuid(), 'canManage' => $service->allowed($r->user(), $workspace, $template->project, true)]);
    }

    public function update(Request $r, Workspace $workspace, string $template, TaskTemplateService $service)
    {
        $service->update($r->user(), $this->bound($workspace, $template), $r->all());

        return back()->with('success', 'Đã cập nhật cấu hình mẫu; lịch sử các kỳ cũ giữ nguyên.');
    }

    public function state(Request $r, Workspace $workspace, string $template, string $action, TaskTemplateService $service)
    {
        $d = $r->validate(['revision' => 'required|integer|min:1']);
        $service->state($r->user(), $this->bound($workspace, $template), $action, (int) $d['revision']);

        return back()->with('success', 'Đã cập nhật trạng thái lịch lặp.');
    }

    public function instantiate(Request $r, Workspace $workspace, string $template, TaskTemplateService $service)
    {
        $d = $r->validate(['request_key' => 'required|uuid', 'revision' => 'required|integer|min:1']);
        $run = $service->instantiate($r->user(), $this->bound($workspace, $template), 'manual:'.$d['request_key'], (int) $d['revision']);

        return back()->with('success', 'Đã tạo bộ công việc mới. Gửi lại cùng yêu cầu không tạo trùng.');
    }
}
