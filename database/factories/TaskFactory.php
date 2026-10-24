<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'task_type' => 'WORK',
            'code' => 'TASK-'.strtoupper(Str::random(5)),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'created_by' => function (array $attributes) {
                return Workspace::find($attributes['workspace_id'])?->owner_id ?? User::factory();
            },
            'assignee_id' => function (array $attributes) {
                return $attributes['created_by'];
            },
            'reviewer_id' => null,
            'status' => fake()->randomElement(['TODO', 'IN_PROGRESS', 'REVIEW', 'DONE']),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'sort_order' => fake()->numberBetween(1, 50),
            'estimate_minutes' => fake()->randomElement([30, 60, 90, 120, 240]),
            'deadline_at' => now()->addDays(fake()->numberBetween(3, 30)),
        ];
    }

    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'task_type' => 'GROUP',
            'code' => 'GRP-'.strtoupper(Str::random(5)),
        ]);
    }

    public function milestone(): static
    {
        return $this->state(fn (array $attributes) => [
            'task_type' => 'MILESTONE',
            'code' => 'MLS-'.strtoupper(Str::random(5)),
        ]);
    }
}
