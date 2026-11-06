<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Task\TaskTemplateQuery;
use App\Services\Task\TaskTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskTemplateController extends Controller
{
    public function __construct(private TaskTemplateService $service, private TaskTemplateQuery $query) {}

    public function index(Request $r, Workspace $workspace)
    {
        $d = $r->validate(['project_id' => 'nullable|uuid']);
        $project = empty($d['project_id']) ? null : $workspace->projects()->findOrFail($d['project_id']);

        return view('templates.index', $this->query->index($r->user(), $workspace, $project));
    }

    public function store(Request $r, Workspace $workspace)
    {
        $d = $r->validate(['project_id' => 'nullable|uuid']);
        $project = empty($d['project_id']) ? null : $workspace->projects()->findOrFail($d['project_id']);
        $t = $this->service->create($r->user(), $workspace, $project, $r->all());

        return to_route('workspaces.templates.show', [$workspace, $t->id])->with('success', 'Đã lưu mẫu độc lập. Chưa tạo task mới.');
    }

    public function show(Request $r, Workspace $workspace, string $template)
    {
        $template = $this->query->find($workspace, $template);
        $data = $this->query->show($r->user(), $workspace, $template);
        $r->attributes->set('scope_project', $template->project);

        return view('templates.show', $data + ['requestKey' => (string) Str::uuid()]);
    }

    public function update(Request $r, Workspace $workspace, string $template)
    {
        $this->service->update($r->user(), $this->query->find($workspace, $template), $r->all());

        return back()->with('success', 'Đã cập nhật cấu hình mẫu; lịch sử các kỳ cũ giữ nguyên.');
    }

    public function state(Request $r, Workspace $workspace, string $template, string $action)
    {
        $d = $r->validate(['revision' => 'required|integer|min:1']);
        $this->service->state($r->user(), $this->query->find($workspace, $template), $action, (int) $d['revision']);

        return back()->with('success', 'Đã cập nhật trạng thái lịch lặp.');
    }

    public function instantiate(Request $r, Workspace $workspace, string $template)
    {
        $d = $r->validate(['request_key' => 'required|uuid', 'revision' => 'required|integer|min:1']);
        $this->service->instantiate($r->user(), $this->query->find($workspace, $template), 'manual:'.$d['request_key'], (int) $d['revision']);

        return back()->with('success', 'Đã tạo bộ công việc mới. Gửi lại cùng yêu cầu không tạo trùng.');
    }
}
