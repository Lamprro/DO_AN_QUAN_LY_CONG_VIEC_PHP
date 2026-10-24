<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
class TaskActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'actor_id' => function (array $attributes) {
                return Task::find($attributes['task_id'])?->created_by ?? User::factory();
            },
            'type' => 'COMMENT',
            'event_type' => null,
            'content' => fake()->sentence(),
            'data' => null,
            'source' => 'user',
        ];
    }
}
