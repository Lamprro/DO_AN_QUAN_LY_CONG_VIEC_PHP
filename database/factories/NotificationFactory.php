<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'recipient_id' => function (array $attributes) {
                return Workspace::find($attributes['workspace_id'])?->owner_id ?? User::factory();
            },
            'type' => 'task_assigned',
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(6),
            'dedupe_key' => 'notif_'.Str::random(16),
            'deliveries' => [],
            'read_at' => null,
        ];
    }
}
