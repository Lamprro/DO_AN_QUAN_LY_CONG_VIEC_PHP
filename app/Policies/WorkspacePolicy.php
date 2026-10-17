<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceAccess;

class WorkspacePolicy
{
    public function __construct(private WorkspaceAccess $access) {}

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->access->allows($user, $workspace, 'workspace.view');
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->access->allows($user, $workspace, 'workspace.update');
    }

    public function manageRoles(User $user, Workspace $workspace): bool
    {
        return $this->access->allows($user, $workspace, 'workspace.manage_roles');
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->access->allows($user, $workspace, 'workspace.manage_members');
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id && $this->view($user, $workspace);
    }

    public function transfer(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id && $this->view($user, $workspace);
    }
}
