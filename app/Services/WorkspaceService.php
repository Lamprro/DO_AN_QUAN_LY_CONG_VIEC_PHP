<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use App\Enums\WorkspaceKind;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WorkspaceService
{
    public function __construct(private WorkspaceAccess $access) {}

    public function create(User $owner, string $name, string $timezone, bool $personal = false): Workspace
    {
        return DB::transaction(function () use ($owner, $name, $timezone, $personal) {
            $workspace = Workspace::create(['name' => $name, 'timezone' => $timezone, 'owner_id' => $owner->id, 'kind' => $personal ? 'personal' : 'team']);
            foreach (config('permissions.defaults') as $roleName => $permissions) {
                $role = Role::create(['workspace_id' => $workspace->id, 'name' => $roleName, 'permissions' => $permissions, 'is_system' => true, 'created_by' => $owner->id]);
                if ($roleName === 'Owner') {
                    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now()]);
                }
            }

            return $workspace;
        });
    }

    public function update(User $actor, Workspace $workspace, array $data): Workspace
    {
        return DB::transaction(function () use ($actor, $workspace, $data) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            Gate::forUser($actor)->authorize('update', $workspace);
            $workspace->update(array_intersect_key($data, array_flip(['name', 'timezone'])));

            return $workspace;
        });
    }

    public function transfer(User $actor, Workspace $workspace, WorkspaceMember $target): void
    {
        DB::transaction(function () use ($actor, $workspace, $target) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            Gate::forUser($actor)->authorize('transfer', $workspace);
            if ($workspace->kind === WorkspaceKind::PERSONAL) {
                throw ValidationException::withMessages(['member_id' => 'Không thể chuyển chủ workspace cá nhân.']);
            }
            $target = $workspace->members()->with('user')->lockForUpdate()->findOrFail($target->id);
            if ($target->user_id === $actor->id || $target->status !== MembershipStatus::ACTIVE || $target->user->status !== UserStatus::ACTIVE) {
                throw ValidationException::withMessages(['member_id' => 'Hãy chọn thành viên khác đang hoạt động.']);
            }
            $ownerRole = $workspace->roles()->where('is_system', true)->where('name', 'Owner')->firstOrFail();
            $managerRole = $workspace->roles()->where('is_system', true)->where('name', 'Manager')->firstOrFail();
            $oldMember = $workspace->members()->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            $workspace->update(['owner_id' => $target->user_id]);
            $target->update(['role_id' => $ownerRole->id]);
            $oldMember->update(['role_id' => $managerRole->id]);
        });
    }

    public function delete(User $actor, Workspace $workspace): void
    {
        DB::transaction(function () use ($actor, $workspace) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            Gate::forUser($actor)->authorize('delete', $workspace);
            if ($workspace->kind === WorkspaceKind::PERSONAL) {
                throw ValidationException::withMessages(['workspace' => 'Không được xóa workspace cá nhân.']);
            }
            foreach (['projects', 'tasks', 'ai_runs', 'plans', 'calendar_events', 'notifications', 'report_snapshots', 'integration_connections', 'calendar_proposals', 'task_templates'] as $table) {
                if (Schema::hasTable($table) && DB::table($table)->where('workspace_id', $workspace->id)->exists()) {
                    throw ValidationException::withMessages(['workspace' => 'Workspace có dữ liệu nghiệp vụ; không thể xóa.']);
                }
            }
            if (app(InvitationService::class)->pending($workspace)) {
                throw ValidationException::withMessages(['workspace' => 'Hãy thu hồi lời mời đang chờ trước khi xóa.']);
            }
            $workspace->members()->delete();
            $workspace->roles()->delete();
            $workspace->delete();
        });
    }

    public function updateMember(User $actor, Workspace $workspace, WorkspaceMember $member, array $data): void
    {
        DB::transaction(function () use ($actor, $workspace, $member, $data) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            Gate::forUser($actor)->authorize('manageMembers', $workspace);
            $member = $workspace->members()->with('role', 'user')->lockForUpdate()->findOrFail($member->id);
            if ($member->user_id === $workspace->owner_id || $member->user_id === $actor->id) {
                throw ValidationException::withMessages(['member' => 'Không được sửa owner hoặc tự đổi vai trò của mình qua chức năng này.']);
            }
            $this->access->assertGrantable($actor, $workspace, $member->role->permissions);
            $role = $workspace->roles()->findOrFail($data['role_id']);
            $this->access->assertAssignable($actor, $workspace, $role);
            if ($data['status'] === 'active' && $member->user->status !== UserStatus::ACTIVE) {
                throw ValidationException::withMessages(['status' => 'Tài khoản này đang bị vô hiệu hóa.']);
            }
            $member->update(['role_id' => $role->id, 'status' => $data['status']]);
        });
    }

    public function removeMember(User $actor, Workspace $workspace, WorkspaceMember $member): void
    {
        $this->updateMember($actor, $workspace, $member, ['role_id' => $member->role_id, 'status' => 'left']);
    }

    public function leave(User $actor, Workspace $workspace): void
    {
        DB::transaction(function () use ($actor, $workspace) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            if ($workspace->owner_id === $actor->id) {
                throw ValidationException::withMessages(['workspace' => 'Owner phải chuyển chủ sở hữu trước khi rời workspace.']);
            }
            $member = $workspace->members()->where('user_id', $actor->id)->where('status', MembershipStatus::ACTIVE)->lockForUpdate()->firstOrFail();
            $member->update(['status' => 'left']);
        });
    }

    public function saveRole(User $actor, Workspace $workspace, array $data, ?Role $role = null): Role
    {
        return DB::transaction(function () use ($actor, $workspace, $data, $role) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            Gate::forUser($actor)->authorize('manageRoles', $workspace);
            $this->access->assertGrantable($actor, $workspace, $data['permissions']);
            if ($workspace->roles()->where('name', $data['name'])->when($role, fn ($query) => $query->whereKeyNot($role->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'Tên vai trò đã được sử dụng trong workspace.']);
            }
            if ($role) {
                $role = $workspace->roles()->lockForUpdate()->findOrFail($role->id);
                Gate::forUser($actor)->authorize('update', $role);
                $this->access->assertGrantable($actor, $workspace, $role->permissions);
                $role->update(['name' => $data['name'], 'permissions' => array_values(array_unique($data['permissions']))]);
            } else {
                $role = Role::create(['workspace_id' => $workspace->id, 'name' => $data['name'], 'permissions' => array_values(array_unique($data['permissions'])), 'is_system' => false, 'created_by' => $actor->id]);
            }

            return $role;
        });
    }

    public function deleteRole(User $actor, Workspace $workspace, Role $role): void
    {
        DB::transaction(function () use ($actor, $workspace, $role) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            $role = $workspace->roles()->lockForUpdate()->findOrFail($role->id);
            Gate::forUser($actor)->authorize('delete', $role);
            $this->access->assertGrantable($actor, $workspace, $role->permissions);
            if ($role->workspaceMembers()->exists() || $role->projectMembers()->exists()) {
                throw ValidationException::withMessages(['role' => 'Vai trò đang được gán cho thành viên; không thể xóa.']);
            }
            $role->delete();
        });
    }
}
