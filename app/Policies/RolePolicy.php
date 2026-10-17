<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\WorkspaceAccess;

class RolePolicy
{
    public function __construct(private WorkspaceAccess $access) {}

    public function update(User $user, Role $role): bool
    {
        return ! $role->is_system && $this->access->allows($user, $role->workspace, 'workspace.manage_roles');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->update($user, $role);
    }
}
