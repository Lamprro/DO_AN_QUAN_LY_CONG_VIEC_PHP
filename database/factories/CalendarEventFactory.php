<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 14))->setHour(9)->setMinute(0)->setSecond(0);

        return [
            'workspace_id' => function (array $attributes) {
                if (! empty($attributes['project_id'])) {
                    return Project::find($attributes['project_id'])?->workspace_id ?? Workspace::factory();
                }

                return Workspace::factory();
            },
            'project_id' => null,
            'user_id' => function (array $attributes) {
                return Workspace::find($attributes['workspace_id'])?->owner_id ?? User::factory();
            },
            'event_type' => 'NOTE',
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(1),
            'timezone' => 'Asia/Ho_Chi_Minh',
            'all_day' => false,
            'is_busy' => false,
            'visibility' => 'project',
            'status' => 'planned',
            'source' => 'manual',
        ];
    }
}
