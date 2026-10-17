<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\ProjectAccess;

class ProjectPolicy
{
    public function __construct(private ProjectAccess $access) {}

    public function view(User $user, Project $project): bool
    {
        return $this->access->allows($user, $project, 'project.view');
    }

    public function update(User $user, Project $project): bool
    {
        return ! $project->archived_at && $this->access->allows($user, $project, 'project.update');
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return ! $project->archived_at && $this->access->allows($user, $project, 'project.manage_members');
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->access->allows($user, $project, 'project.archive');
    }

    public function delete(User $user, Project $project): bool
    {
        return ! $project->archived_at && $this->archive($user, $project);
    }
}
