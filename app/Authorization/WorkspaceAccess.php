<?php

namespace App\Authorization;

use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Workspace-level permission source. Registered as a scoped singleton, so a
 * membership is read once per request. Reads inside a DB transaction always go
 * to the database: services re-check permissions under row locks.
 */
class WorkspaceAccess
{
    /** @var array<string, WorkspaceMember|null> */
    private array $memberships = [];

    public function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        if ($user->status !== UserStatus::ACTIVE) {
            return null;
        }
        $key = $user->id.'|'.$workspace->id;
        if (DB::transactionLevel() === 0 && array_key_exists($key, $this->memberships)) {
            return $this->memberships[$key];
        }
        $member = WorkspaceMember::query()->with('role')->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)->where('status', MembershipStatus::ACTIVE)->first();
        if (DB::transactionLevel() === 0) {
            $this->memberships[$key] = $member;
        }

        return $member;
    }

    /** Workspaces where the user is an active member holding workspace.view, ordered by name. */
    public function visible(User $user): Collection
    {
        return Workspace::whereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->orderBy('name')->get()->filter(fn (Workspace $w) => $this->allows($user, $w, 'workspace.view'))->values();
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

    /** Roles the actor may hand out (never Owner, never more than the actor holds). */
    public function assignableRoles(User $actor, Workspace $workspace, ?array $actorPermissions = null)
    {
        $actorPermissions ??= $this->permissions($actor, $workspace);

        return $workspace->roles()->orderBy('name')->get()
            ->filter(fn (Role $role) => ! ($role->is_system && $role->name === 'Owner') && ! array_diff($role->permissions, $actorPermissions));
    }

    public function flush(): void
    {
        $this->memberships = [];
    }
}
