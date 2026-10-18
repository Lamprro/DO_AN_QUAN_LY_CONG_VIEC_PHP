<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Models\Workspace;
use App\Services\RoleService;
use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    private function groups(array $permissions): array
    {
        $modules = ['workspace' => 'Workspace', 'project' => 'Project', 'task' => 'Công việc', 'calendar' => 'Lịch', 'plan' => 'Kế hoạch', 'report' => 'Báo cáo', 'integration' => 'Tích hợp'];
        $actions = ['view_operations' => 'Xem vận hành (chỉ run/job được phép)', 'view' => 'Xem', 'create' => 'Tạo', 'update' => 'Sửa', 'archive' => 'Lưu trữ', 'manage_members' => 'Quản lý thành viên', 'manage_roles' => 'Quản lý vai trò', 'assign' => 'Phân công', 'review' => 'Duyệt công việc', 'comment' => 'Bình luận', 'attach' => 'Đính kèm tệp', 'delete' => 'Xóa', 'approve' => 'Duyệt kế hoạch', 'apply' => 'Áp dụng kế hoạch'];
        $result = [];
        foreach ($permissions as $permission) {
            [$module,$action] = explode('.', $permission, 2);
            $label = $modules[$module] ?? $module;
            $result[$label][] = ['value' => $permission, 'label' => $actions[$action] ?? $action];
        }

        return $result;
    }

    public function index(Workspace $workspace): View
    {
        $this->authorize('manageRoles', $workspace);

        return view('roles.index', ['workspace' => $workspace, 'roles' => $workspace->roles()->withCount('workspaceMembers', 'projectMembers')->orderByDesc('is_system')->orderBy('name')->get()]);
    }

    public function create(Request $request, Workspace $workspace, WorkspaceAccess $access): View
    {
        $this->authorize('manageRoles', $workspace);

        return view('roles.create', ['workspace' => $workspace, 'permissionCatalog' => $access->permissions($request->user(), $workspace), 'permissionGroups' => $this->groups($access->permissions($request->user(), $workspace))]);
    }

    public function store(RoleRequest $request, Workspace $workspace, RoleService $service): RedirectResponse
    {
        $service->save($request->user(), $workspace, $request->validated());

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã tạo vai trò.');
    }

    public function edit(Request $request, Workspace $workspace, Role $role, WorkspaceAccess $access): View
    {
        $this->authorize('update', $role);

        return view('roles.edit', ['workspace' => $workspace, 'role' => $role, 'permissionCatalog' => $access->permissions($request->user(), $workspace), 'permissionGroups' => $this->groups($access->permissions($request->user(), $workspace))]);
    }

    public function update(RoleRequest $request, Workspace $workspace, Role $role, RoleService $service): RedirectResponse
    {
        $service->save($request->user(), $workspace, $request->validated(), $role);

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã cập nhật vai trò.');
    }

    public function destroy(Request $request, Workspace $workspace, Role $role, RoleService $service): RedirectResponse
    {
        $service->delete($request->user(), $workspace, $role);

        return to_route('workspaces.roles.index', $workspace)->with('success', 'Đã xóa vai trò chưa sử dụng.');
    }
}
