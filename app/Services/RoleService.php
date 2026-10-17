<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(private WorkspaceAccess $access) {}

    public function save(User $actor, Workspace $workspace, array $data, ?Role $role = null): Role
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

    public function delete(User $actor, Workspace $workspace, Role $role): void
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
