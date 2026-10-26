<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectMemberRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Workspace;
use App\Services\ProjectService;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    public function __construct(private ProjectService $service, private ProjectAccess $access) {}

    public function index(Request $request, Workspace $workspace, Project $project)
    {
        $this->authorize('manageMembers', $project);
        $actorPermissions = $this->access->permissions($request->user(), $project);
        $roles = $workspace->roles()->orderBy('name')->get()->filter(fn ($r) => ! ($r->is_system && $r->name === 'Owner') && ! array_diff($r->permissions, $actorPermissions));
        $members = $project->members()->with('workspaceMember.user', 'role')->get();
        $candidates = $workspace->members()->with('user')->where('status', 'active')->where('user_id', '!=', $request->user()->id)->where('user_id', '!=', $workspace->owner_id)->whereHas('user', fn ($q) => $q->where('status', 'active'))->whereDoesntHave('projectMemberships', fn ($q) => $q->where('project_id', $project->id)->where('status', '!=', 'left'))->get();

        return view('project-members.index', compact('workspace', 'project', 'members', 'roles', 'candidates', 'actorPermissions'));
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
