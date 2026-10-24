<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMember>
 */
class ProjectMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'workspace_member_id' => function (array $attributes) {
                $project = Project::find($attributes['project_id']);
                $workspaceId = $project?->workspace_id;

                return WorkspaceMember::where('workspace_id', $workspaceId)->first()?->id
                    ?? WorkspaceMember::factory()->create(['workspace_id' => $workspaceId])->id;
            },
            'role_id' => function (array $attributes) {
                $wm = WorkspaceMember::find($attributes['workspace_member_id']);

                return $wm?->role_id ?? Role::factory();
            },
            'status' => 'active',
            'joined_at' => now(),
        ];
    }
}
