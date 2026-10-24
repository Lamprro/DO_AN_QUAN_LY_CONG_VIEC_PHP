<?php

namespace App\Services;

use App\Models\User;

class WorkHoursService
{
    public function values(User $user, ?string $timezone = null): array
    {
        return app(TimeTrackingService::class)->workHoursValues($user, $timezone);
    }

    public function version(User $user): string
    {
        return app(TimeTrackingService::class)->workHoursVersion($user);
    }

    public function save(User $user, array $data): void
    {
        app(TimeTrackingService::class)->saveWorkHours($user, $data);
    }
}
