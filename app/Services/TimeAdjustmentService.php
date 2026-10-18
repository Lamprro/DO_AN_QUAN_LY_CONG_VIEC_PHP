<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\TimeEntryAdjustment;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeAdjustmentService
{
    public function allowed(User $actor, TimeEntry $entry): bool
    {
        return $entry->user_id === $actor->id && $entry->ended_at !== null && $actor->can('view', $entry->task);
    }

    public function value(TimeEntry $entry): array
    {
        return ['started_at' => $entry->started_at->utc()->format('Y-m-d H:i:s'), 'ended_at' => $entry->ended_at?->utc()->format('Y-m-d H:i:s'), 'note' => $entry->note];
    }

    public function version(TimeEntry $entry): string
    {
        return hash('sha256', json_encode([$this->value($entry), TimeEntryAdjustment::where('time_entry_id', $entry->id)->count()]));
    }

    private function local(string $input, string $zone): CarbonImmutable
    {
        // Reuse CalendarService's strict local-time validation, including DST ambiguity.
        return app(CalendarService::class)->localTime(substr($input, 0, 16), $zone, false)->addSeconds((int) substr($input, 17, 2));
    }

    public function save(User $actor, TimeEntry $entry, array $data): void
    {
        DB::transaction(function () use ($actor, $entry, $data) {
            User::lockForUpdate()->findOrFail($actor->id);
            Workspace::lockForUpdate()->findOrFail($entry->task->workspace_id);
            $entry = TimeEntry::lockForUpdate()->findOrFail($entry->id);
            abort_unless($this->allowed($actor, $entry), 403);
            if (! hash_equals($this->version($entry), $data['version'])) {
                throw ValidationException::withMessages(['version' => 'Phiên đã thay đổi; tải lại để đối chiếu.']);
            }
            if ($data['timezone'] !== $actor->timezone) {
                throw ValidationException::withMessages(['timezone' => 'Múi giờ hồ sơ đã đổi; tải lại form.']);
            }
            $start = $this->local($data['started_at'], $actor->timezone);
            $end = $this->local($data['ended_at'], $actor->timezone);
            if ($end->lte($start) || $end->gt(now())) {
                throw ValidationException::withMessages(['ended_at' => 'Kết thúc phải sau bắt đầu và không ở tương lai.']);
            }
            if (TimeEntry::where('user_id', $actor->id)->whereKeyNot($entry->id)->where('started_at', '<', $end)->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $start))->exists()) {
                throw ValidationException::withMessages(['started_at' => 'Khoảng này trùng phiên giờ khác của bạn.']);
            }
            $before = $this->value($entry);
            $entry->fill(['started_at' => $start, 'ended_at' => $end, 'note' => $data['note'] ?? null]);
            if (! $entry->isDirty()) {
                return;
            }$entry->save();
            $after = $this->value($entry);
            $audit = TimeEntryAdjustment::create(['time_entry_id' => $entry->id, 'actor_id' => $actor->id, 'before' => $before, 'after' => $after, 'reason' => $data['reason'], 'created_at' => now()]);
            app(TaskHistory::class)->record($actor, $entry->task, 'timer.corrected', ['time_entry_id' => $entry->id, 'adjustment_id' => $audit->id, 'before' => $before, 'after' => $after, 'reason' => $data['reason']]);
        }, 3);
    }
}
