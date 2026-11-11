<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Http\Requests\RoleRequest;
use App\Http\Requests\TransferOwnerRequest;
use App\Http\Requests\WorkspaceRequest;
use App\Models\Role;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceService $service) {}

    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:160']]);

        return view('workspaces.index', ['workspaces' => $this->service->index($request->user(), $request->input('q'))]);
    }

    public function create(): View
    {
        return view('workspaces.create', ['timezones' => timezone_identifiers_list()]);
    }

    public function store(WorkspaceRequest $request): RedirectResponse
    {
        $workspace = $this->service->create($request->user(), $request->validated('name'), $request->validated('timezone'));
        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('workspaces.show', $workspace)->with('success', 'Đã tạo workspace nhóm.');
    }

    public function show(Request $request, Workspace $workspace): View
    {
        $this->authorize('view', $workspace);

        return view('workspaces.show', $this->service->overview($request->user(), $workspace));
    }

    public function edit(Workspace $workspace): View
    {
        $this->authorize('update', $workspace);

        return view('workspaces.edit', ['workspace' => $workspace, 'timezones' => timezone_identifiers_list()]);
    }

    public function update(WorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->service->update($request->user(), $workspace, $request->validated());

        return to_route('workspaces.show', $workspace)->with('success', 'Đã cập nhật workspace.');
    }

    public function select(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('view', $workspace);
        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('workspaces.show', $workspace);
    }

    public function transfer(TransferOwnerRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->service->transfer($request->user(), $workspace, $workspace->members()->findOrFail($request->validated('member_id')));

        return to_route('workspaces.show', $workspace)->with('success', 'Đã chuyển chủ sở hữu. Quyền của bạn đã được cập nhật.');
    }

    public function leave(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->service->leave($request->user(), $workspace);
        $request->session()->forget('current_workspace_id');

        return to_route('workspaces.index')->with('success', 'Đã rời workspace.');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->service->delete($request->user(), $workspace);
        $request->session()->forget('current_workspace_id');

        return to_route('workspaces.index')->with('success', 'Đã xóa workspace trống.');
    }

    public function members(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageMembers', $workspace);

        return view('members.index', $this->service->members($request->user(), $workspace));
    }

    public function updateMember(MemberRequest $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->service->updateMember($request->user(), $workspace, $member, $request->validated());

        return to_route('workspaces.members.index', $workspace)->with('success', 'Đã cập nhật thành viên.');
    }

    public function removeMember(Request $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->service->removeMember($request->user(), $workspace, $member);

        return to_route('workspaces.members.index', $workspace)->with('success', 'Đã đánh dấu thành viên rời workspace; lịch sử được giữ lại.');
    }

    public function roles(Workspace $workspace): View
    {
        $this->authorize('manageRoles', $workspace);

        return view('roles.index', ['workspace' => $workspace, 'roles' => $this->service->roles($workspace)]);
    }

    public function createRole(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageRoles', $workspace);

        return view('roles.create', ['workspace' => $workspace] + $this->service->grantablePermissions($request->user(), $workspace));
    }

    public function storeRole(RoleRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->service->saveRole($request->user(), $workspace, $request->validated());

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã tạo vai trò.');
    }

    public function editRole(Request $request, Workspace $workspace, Role $role): View
    {
        $this->authorize('update', $role);

        return view('roles.edit', ['workspace' => $workspace, 'role' => $role] + $this->service->grantablePermissions($request->user(), $workspace));
    }

    public function updateRole(RoleRequest $request, Workspace $workspace, Role $role): RedirectResponse
    {
        $this->service->saveRole($request->user(), $workspace, $request->validated(), $role);

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã cập nhật vai trò.');
    }

    public function destroyRole(Request $request, Workspace $workspace, Role $role): RedirectResponse
    {
        $this->service->deleteRole($request->user(), $workspace, $role);

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã xóa vai trò chưa sử dụng.');
    }
}
