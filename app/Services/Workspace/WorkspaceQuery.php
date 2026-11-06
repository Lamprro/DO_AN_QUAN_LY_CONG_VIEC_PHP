<?php

namespace App\Services\Workspace;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SearchTerm;
use Illuminate\Support\Collection;

/** Read model for workspace, member and role screens. */
class WorkspaceQuery
{
    public function __construct(private WorkspaceAccess $access, private ProjectAccess $projects, private InvitationService $invitations) {}

    public function index(User $user, ?string $search)
    {
        return Workspace::query()->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('status', 'active')->whereHas('role', fn ($r) => $r->whereJsonContains('permissions', 'workspace.view'))))
            ->when($search !== null && $search !== '', fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [SearchTerm::contains($search)]))->latest()->paginate(12)->withQueryString();
    }

    /** Overview counts only include projects/tasks the user may see. */
    public function overview(User $user, Workspace $workspace): array
    {
        $workspace->load('owner')->loadCount('members', 'roles', 'projects', 'tasks');
        $visible = $this->projects->visible($user, $workspace)->get();
        $workspace->setAttribute('projects_count', $visible->count());
        $taskProjects = $visible->filter(fn ($p) => $this->projects->allows($user, $p, 'task.view'))->pluck('id');
        $workspace->setAttribute('tasks_count', $workspace->tasks()->where(function ($q) use ($taskProjects, $workspace, $user) {
            $q->whereIn('project_id', $taskProjects);
            if ($workspace->kind->value === 'personal' && $workspace->owner_id === $user->id) {
                $q->orWhereNull('project_id');
            }
        })->count());

        return [
            'workspace' => $workspace,
            'membership' => $this->access->membership($user, $workspace),
            'transferCandidates' => $workspace->owner_id === $user->id ? $this->activeMembers($workspace)->where('user_id', '!=', $user->id)->values() : collect(),
        ];
    }

    public function members(User $actor, Workspace $workspace): array
    {
        $permissions = $this->access->permissions($actor, $workspace);

        return [
            'workspace' => $workspace,
            'members' => $workspace->members()->with('user', 'role')->orderBy('joined_at')->get(),
            'assignableRoles' => $this->access->assignableRoles($actor, $workspace, $permissions),
            'pendingInvitations' => $this->invitations->pending($workspace),
            'actorPermissions' => $permissions,
        ];
    }

    public function roles(Workspace $workspace)
    {
        return $workspace->roles()->withCount('workspaceMembers', 'projectMembers')->orderByDesc('is_system')->orderBy('name')->get();
    }

    /** The actor may only grant permissions they hold; grouped by module for the role form. */
    public function grantablePermissions(User $actor, Workspace $workspace): array
    {
        $permissions = $this->access->permissions($actor, $workspace);
        $modules = ['workspace' => 'Workspace', 'project' => 'Project', 'task' => 'Công việc', 'calendar' => 'Lịch', 'plan' => 'Kế hoạch', 'report' => 'Báo cáo', 'integration' => 'Tích hợp'];
        $actions = ['view_operations' => 'Xem vận hành (chỉ run/job được phép)', 'view' => 'Xem', 'create' => 'Tạo', 'update' => 'Sửa', 'archive' => 'Lưu trữ', 'manage_members' => 'Quản lý thành viên', 'manage_roles' => 'Quản lý vai trò', 'assign' => 'Phân công', 'review' => 'Duyệt công việc', 'comment' => 'Bình luận', 'attach' => 'Đính kèm tệp', 'delete' => 'Xóa', 'approve' => 'Duyệt kế hoạch', 'apply' => 'Áp dụng kế hoạch'];
        $groups = [];
        foreach ($permissions as $permission) {
            [$module, $action] = explode('.', $permission, 2);
            $groups[$modules[$module] ?? $module][] = ['value' => $permission, 'label' => $actions[$action] ?? $action];
        }

        return ['permissionCatalog' => $permissions, 'permissionGroups' => $groups];
    }

    /** Active members whose account is active too (pickers, transfer, reports). */
    public function activeMembers(Workspace $workspace): Collection
    {
        return $workspace->members()->with('user')->where('status', 'active')->whereHas('user', fn ($q) => $q->where('status', 'active'))->get();
    }
}
