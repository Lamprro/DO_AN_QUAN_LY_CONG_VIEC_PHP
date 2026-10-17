<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Validation\ValidationException;

class WorkspaceAccess
{
    public function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        if ($user->status !== UserStatus::ACTIVE) {
            return null;
        }

        return WorkspaceMember::query()->with('role')->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)->where('status', MembershipStatus::ACTIVE)->first();
    }

    public function permissions(User $user, Workspace $workspace): array
    {
        $member = $this->membership($user, $workspace);
        if (! $member) {
            return [];
        }

        return $workspace->owner_id === $user->id
            ? config('permissions.catalog')
            : array_values(array_intersect($member->role->permissions, config('permissions.catalog')));
    }

    public function allows(User $user, Workspace $workspace, string $permission): bool
    {
        return in_array($permission, $this->permissions($user, $workspace), true);
    }

    public function assertGrantable(User $actor, Workspace $workspace, array $permissions): void
    {
        if (array_diff($permissions, $this->permissions($actor, $workspace))) {
            throw ValidationException::withMessages(['permissions' => 'Không được cấp quyền vượt quá quyền hiện có của bạn.']);
        }
    }

    public function assertAssignable(User $actor, Workspace $workspace, Role $role): void
    {
        abort_unless($role->workspace_id === $workspace->id, 404);
        if ($role->is_system && $role->name === 'Owner') {
            throw ValidationException::withMessages(['role_id' => 'Vai trò Owner chỉ được cấp qua chức năng chuyển chủ sở hữu.']);
        }
        $this->assertGrantable($actor, $workspace, $role->permissions);
    }
}
