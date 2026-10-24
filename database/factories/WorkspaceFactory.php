<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Workspace',
            'kind' => 'team',
            'owner_id' => User::factory(),
            'timezone' => 'Asia/Ho_Chi_Minh',
        ];
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'personal',
            'name' => 'Personal Workspace',
        ]);
    }
}
