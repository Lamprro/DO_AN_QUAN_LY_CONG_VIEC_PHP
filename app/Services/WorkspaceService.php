<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use App\Enums\WorkspaceKind;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WorkspaceService
{
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
}
