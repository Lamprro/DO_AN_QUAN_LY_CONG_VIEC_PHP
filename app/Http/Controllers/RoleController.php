<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Models\Workspace;
use App\Services\Workspace\WorkspaceQuery;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private WorkspaceService $service, private WorkspaceQuery $query) {}

    public function index(Workspace $workspace): View
    {
        $this->authorize('manageRoles', $workspace);

        return view('roles.index', ['workspace' => $workspace, 'roles' => $this->query->roles($workspace)]);
    }

    public function create(Request $request, Workspace $workspace): View
    {
        $this->authorize('manageRoles', $workspace);

        return view('roles.create', ['workspace' => $workspace] + $this->query->grantablePermissions($request->user(), $workspace));
    }

    public function store(RoleRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->service->saveRole($request->user(), $workspace, $request->validated());

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã tạo vai trò.');
    }

    public function edit(Request $request, Workspace $workspace, Role $role): View
    {
        $this->authorize('update', $role);

        return view('roles.edit', ['workspace' => $workspace, 'role' => $role] + $this->query->grantablePermissions($request->user(), $workspace));
    }

    public function update(RoleRequest $request, Workspace $workspace, Role $role): RedirectResponse
    {
        $this->service->saveRole($request->user(), $workspace, $request->validated(), $role);

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã cập nhật vai trò.');
    }

    public function destroy(Request $request, Workspace $workspace, Role $role): RedirectResponse
    {
        $this->service->deleteRole($request->user(), $workspace, $role);

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã xóa vai trò chưa sử dụng.');
    }
}
