<?php

namespace App\Authorization;

use App\Models\AiRun;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

/** Plan / planning-AI rules. Plans share the task scope but use plan.* permissions. */
class PlanAccess
{
    public function __construct(private TaskAccess $tasks) {}

    public function allows(User $user, Workspace $workspace, ?Project $project, string $permission = 'plan.view'): bool
    {
        return $this->tasks->scope($user, $workspace, $project, $permission);
    }

    /** Permission plus a writable (not archived/closed) project. */
    public function writable(User $user, Workspace $workspace, ?Project $project, string $permission): bool
    {
        return $this->allows($user, $workspace, $project, $permission) && $this->tasks->projectWritable($project);
    }

    /** May the user see (or, with plan.create, act on) a planning AI run? */
    public function run(User $user, AiRun $run, string $permission = 'plan.view'): bool
    {
        return ($permission !== 'plan.create' || $this->tasks->projectWritable($run->project))
            && ($run->context_snapshot['schema_version'] ?? null) === 1
            && $this->allows($user, $run->workspace, $run->project, $permission);
    }
}
