<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;

class TimeAdjustmentService
{
    public function allowed(User $actor, TimeEntry $entry): bool
    {
        return app(TimeTrackingService::class)->allowed($actor, $entry);
    }

    public function value(TimeEntry $entry): array
    {
        return app(TimeTrackingService::class)->value($entry);
    }

    public function version(TimeEntry $entry): string
    {
        return app(TimeTrackingService::class)->version($entry);
    }

    public function save(User $actor, TimeEntry $entry, array $data): void
    {
        app(TimeTrackingService::class)->saveAdjustment($actor, $entry, $data);
    }
}
