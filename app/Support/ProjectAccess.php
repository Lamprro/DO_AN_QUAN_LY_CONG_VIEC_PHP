<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProjectAccess
{
    public function __construct(private WorkspaceAccess $workspaces) {}

    public function canCreate(User $user, Workspace $workspace): bool
    {
        return $this->workspaces->allows($user, $workspace, 'workspace.view')
            && $this->workspaces->allows($user, $workspace, 'project.view')
            && $this->workspaces->allows($user, $workspace, 'project.create');
    }

    public function permissions(User $user, Project $project): array
    {
        $workspace = $project->workspace;
        if (! $this->workspaces->allows($user, $workspace, 'workspace.view')) {
            return [];
        }
        if ($workspace->owner_id === $user->id) {
            return config('permissions.catalog');
        }
        $member = $project->members()->with('role')->where('status', 'active')
            ->whereHas('workspaceMember', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))->first();

        return $member ? array_values(array_intersect($member->role->permissions, config('permissions.catalog'))) : [];
    }

    public function allows(User $user, Project $project, string $permission): bool
    {
        $permissions = $this->permissions($user, $project);

        return in_array('project.view', $permissions, true) && in_array($permission, $permissions, true);
    }

    public function visible(User $user, Workspace $workspace): Builder
    {
        $query = $workspace->projects();
        if (! $this->workspaces->allows($user, $workspace, 'workspace.view')) {
            return $query->whereRaw('1=0')->getQuery();
        }
        if ($workspace->owner_id !== $user->id) {
            $query->whereHas('members', fn ($m) => $m->where('status', 'active')
                ->whereHas('role', fn ($r) => $r->whereJsonContains('permissions', 'project.view'))
                ->whereHas('workspaceMember', fn ($w) => $w->where('user_id', $user->id)->where('status', 'active')));
        }

        return $query->getQuery();
    }

    public function grantable(User $actor, Project $project, array $permissions): void
    {
        if (array_diff($permissions, $this->permissions($actor, $project))) {
            throw ValidationException::withMessages(['role_id' => 'Không được cấp hoặc quản lý vai trò vượt quyền hiện có trong project.']);
        }
    }

    public function eligibleMembers(Workspace $workspace, ?Project $project, string $permission = 'task.view'): Collection
    {
        return $workspace->members()->with('user')->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->get()->filter(function ($member) use ($workspace, $project, $permission) {
                return $member->user_id === $workspace->owner_id
                    || ($project && $this->allows($member->user, $project, 'task.view') && $this->allows($member->user, $project, $permission));
            })->values();
    }
}
