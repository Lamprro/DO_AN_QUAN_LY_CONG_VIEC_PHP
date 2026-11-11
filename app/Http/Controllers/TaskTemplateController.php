<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Task\TaskTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskTemplateController extends Controller
{
    public function __construct(private TaskTemplateService $service) {}

    public function index(Request $request, Workspace $workspace)
    {
        $d = $request->validate(['project_id' => 'nullable|uuid']);
        $project = empty($d['project_id']) ? null : $workspace->projects()->findOrFail($d['project_id']);

        return view('templates.index', $this->service->index($request->user(), $workspace, $project));
    }

    public function store(Request $request, Workspace $workspace)
    {
        $d = $request->validate(['project_id' => 'nullable|uuid']);
        $project = empty($d['project_id']) ? null : $workspace->projects()->findOrFail($d['project_id']);
        $t = $this->service->create($request->user(), $workspace, $project, $request->all());

        return to_route('workspaces.templates.show', [$workspace, $t->id])->with('success', 'Đã lưu mẫu độc lập. Chưa tạo task mới.');
    }

    public function show(Request $request, Workspace $workspace, string $template)
    {
        $template = $this->service->find($workspace, $template);
        $data = $this->service->show($request->user(), $workspace, $template);
        $request->attributes->set('scope_project', $template->project);

        return view('templates.show', $data + ['requestKey' => (string) Str::uuid()]);
    }

    public function update(Request $request, Workspace $workspace, string $template)
    {
        $this->service->update($request->user(), $this->service->find($workspace, $template), $request->all());

        return back()->with('success', 'Đã cập nhật cấu hình mẫu; lịch sử các kỳ cũ giữ nguyên.');
    }

    public function state(Request $request, Workspace $workspace, string $template, string $action)
    {
        $d = $request->validate(['revision' => 'required|integer|min:1']);
        $this->service->state($request->user(), $this->service->find($workspace, $template), $action, (int) $d['revision']);

        return back()->with('success', 'Đã cập nhật trạng thái lịch lặp.');
    }

    public function instantiate(Request $request, Workspace $workspace, string $template)
    {
        $d = $request->validate(['request_key' => 'required|uuid', 'revision' => 'required|integer|min:1']);
        $this->service->instantiate($request->user(), $this->service->find($workspace, $template), 'manual:'.$d['request_key'], (int) $d['revision']);

        return back()->with('success', 'Đã tạo bộ công việc mới. Gửi lại cùng yêu cầu không tạo trùng.');
    }
}
