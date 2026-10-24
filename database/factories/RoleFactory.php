<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => 'Vai trò '.fake()->jobTitle(),
            'permissions' => config('permissions.defaults.Member', [
                'workspace.view', 'project.view', 'task.view', 'calendar.view',
            ]),
            'is_system' => false,
            'created_by' => function (array $attributes) {
                return Workspace::find($attributes['workspace_id'])?->owner_id ?? User::factory();
            },
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }
}
