<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    /** Only the owner may correct a finished entry, and only while the task is still visible to them. */
    public function adjust(User $user, TimeEntry $entry): bool
    {
        return $entry->user_id === $user->id && $entry->ended_at !== null && $user->can('view', $entry->task);
    }
}
