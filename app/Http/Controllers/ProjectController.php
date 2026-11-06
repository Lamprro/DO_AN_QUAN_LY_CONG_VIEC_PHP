<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\Project\ProjectQuery;
use App\Services\Project\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $service, private ProjectQuery $query) {}

    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);
        $filters = $request->validate(['view' => ['nullable', Rule::in(['cards', 'table'])], 'q' => ['nullable', 'string', 'max:180'], 'status' => ['nullable', Rule::enum(ProjectStatus::class)], 'archived' => ['nullable', Rule::in(['active', 'archived', 'all'])], 'sort' => ['nullable', Rule::in(['name', 'created_at', 'deadline'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);

        return view('projects.index', $this->query->index($request->user(), $workspace, $filters));
    }

    public function create(Request $request, Workspace $workspace)
    {
        return view('projects.create', $this->query->form($request->user(), $workspace));
    }

    public function store(ProjectRequest $request, Workspace $workspace)
    {
        $project = $this->service->save($request->user(), $workspace, $request->validated());

        return to_route('workspaces.projects.show', [$workspace, $project])->with('success', 'Đã tạo project và membership của người tạo.');
    }

    public function show(Request $request, Workspace $workspace, Project $project)
    {
        $this->authorize('view', $project);

        return view('projects.show', $this->query->overview($request->user(), $workspace, $project));
    }

    public function edit(Request $request, Workspace $workspace, Project $project)
    {
        if ($request->boolean('fresh')) {
            $request->session()->forget('_old_input');

            return redirect()->to($request->fullUrlWithoutQuery(['fresh']));
        }
        $this->authorize('update', $project);

        return view('projects.edit', $this->query->form($request->user(), $workspace, $project));
    }

    public function update(ProjectRequest $request, Workspace $workspace, Project $project)
    {
        $this->service->save($request->user(), $workspace, $request->validated(), $project);

        return to_route('workspaces.projects.show', [$workspace, $project])->with('success', 'Đã cập nhật project.');
    }

    public function archive(Request $request, Workspace $workspace, Project $project)
    {
        $this->service->archive($request->user(), $workspace, $project);

        return back()->with('success', 'Đã lưu trữ project.');
    }

    public function restore(Request $request, Workspace $workspace, Project $project)
    {
        $this->service->archive($request->user(), $workspace, $project, true);

        return back()->with('success', 'Đã khôi phục project.');
    }

    public function destroy(Request $request, Workspace $workspace, Project $project)
    {
        $this->service->delete($request->user(), $workspace, $project);

        return to_route('workspaces.projects.index', $workspace)->with('success', 'Đã xóa project nháp trống.');
    }
}
