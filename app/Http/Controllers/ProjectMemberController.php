<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectMemberRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Workspace;
use App\Services\Project\ProjectQuery;
use App\Services\Project\ProjectService;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    public function __construct(private ProjectService $service, private ProjectQuery $query) {}

    public function index(Request $request, Workspace $workspace, Project $project)
    {
        $this->authorize('manageMembers', $project);

        return view('project-members.index', $this->query->members($request->user(), $workspace, $project));
    }

    public function store(ProjectMemberRequest $request, Workspace $workspace, Project $project)
    {
        $this->service->saveMember($request->user(), $workspace, $project, $request->validated());

        return back()->with('success', 'Đã thêm/kích hoạt lại thành viên project.');
    }

    public function update(ProjectMemberRequest $request, Workspace $workspace, Project $project, ProjectMember $projectMember)
    {
        $this->service->saveMember($request->user(), $workspace, $project, $request->validated(), $projectMember);

        return back()->with('success', 'Đã cập nhật thành viên project.');
    }

    public function destroy(Request $request, Workspace $workspace, Project $project, ProjectMember $projectMember)
    {
        $this->service->removeMember($request->user(), $workspace, $project, $projectMember);

        return back()->with('success', 'Đã đánh dấu thành viên rời project.');
    }
}
