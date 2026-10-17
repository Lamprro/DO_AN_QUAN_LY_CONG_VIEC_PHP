<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TimeTrackingService
{
    public function start(User $actor, Task $task): TimeEntry
    {
        return DB::transaction(function () use ($actor, $task) {
            User::lockForUpdate()->findOrFail($actor->id);
            Workspace::lockForUpdate()->findOrFail($task->workspace_id);
            $task = Task::lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize('execute', $task);
            if ($task->task_type->value !== 'WORK' || $task->status->value !== 'IN_PROGRESS' || $task->assignee_id !== $actor->id) {
                throw ValidationException::withMessages(['timer' => 'Chỉ bắt đầu timer WORK đang làm và được giao cho bạn.']);
            }
            if (TimeEntry::where('user_id', $actor->id)->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['timer' => 'Bạn đang có timer khác. Hãy dừng timer trước.']);
            }
            if ($task->blockers()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['timer' => 'Cần xử lý blocker trước.']);
            }
            $entry = TimeEntry::create(['task_id' => $task->id, 'user_id' => $actor->id, 'started_at' => now()]);
            app(TaskHistory::class)->record($actor, $task, 'timer.started', ['time_entry_id' => $entry->id]);

            return $entry;
        }, 3);
    }

    public function stop(User $actor, TimeEntry $entry): void
    {
        DB::transaction(function () use ($actor, $entry) {
            User::lockForUpdate()->findOrFail($actor->id);
            $entry = TimeEntry::lockForUpdate()->findOrFail($entry->id);
            abort_unless($entry->user_id === $actor->id, 403);
            if ($entry->ended_at) {
                return;
            }
            // Owner may stop their clock after membership revocation; no hidden task is returned.
            if (now()->lte($entry->started_at)) {
                throw ValidationException::withMessages(['timer' => 'Đợi ít nhất một giây trước khi dừng timer.']);
            }
            $entry->update(['ended_at' => now()]);
            app(TaskHistory::class)->record($actor, $entry->task, 'timer.stopped', ['time_entry_id' => $entry->id]);
        }, 3);
    }
}
