<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => function (array $attributes) {
                if (! empty($attributes['project_id'])) {
                    return Project::find($attributes['project_id'])?->workspace_id ?? Workspace::factory();
                }

                return Workspace::factory();
            },
            'project_id' => function (array $attributes) {
                return Project::where('workspace_id', $attributes['workspace_id'])->first()?->id
                    ?? Project::factory()->create(['workspace_id' => $attributes['workspace_id']])->id;
            },
            'name' => 'Kế hoạch '.fake()->catchPhrase(),
            'version_no' => 1,
            'plan_group_id' => (string) Str::uuid(),
            'data' => [
                'schema_version' => 1,
                'tasks' => [],
                'events' => [],
            ],
            'status' => 'proposed',
            'is_active' => false,
            'created_by' => function (array $attributes) {
                return Workspace::find($attributes['workspace_id'])?->owner_id ?? User::factory();
            },
        ];
    }
}
