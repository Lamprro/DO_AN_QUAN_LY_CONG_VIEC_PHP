<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceMember>
 */
class WorkspaceMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'role_id' => function (array $attributes) {
                return Role::where('workspace_id', $attributes['workspace_id'])->first()?->id
                    ?? Role::factory()->create(['workspace_id' => $attributes['workspace_id']])->id;
            },
            'status' => 'active',
            'joined_at' => now(),
        ];
    }
}
