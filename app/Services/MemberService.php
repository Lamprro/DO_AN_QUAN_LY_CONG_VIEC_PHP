<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function __construct(private WorkspaceAccess $access) {}

    public function update(User $actor, Workspace $workspace, WorkspaceMember $member, array $data): void
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

    public function remove(User $actor, Workspace $workspace, WorkspaceMember $member): void
    {
        $this->update($actor, $workspace, $member, ['role_id' => $member->role_id, 'status' => 'left']);
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
}
