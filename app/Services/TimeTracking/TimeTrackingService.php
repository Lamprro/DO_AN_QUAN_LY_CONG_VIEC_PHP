<?php

namespace App\Services\TimeTracking;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\TimeEntryAdjustment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Setting\SettingService;
use App\Services\Task\TaskHistory;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TimeTrackingService
{
    public function __construct(private TaskHistory $history, private SettingService $settings) {}

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
            $entry = TimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'started_at' => now(),
            ]);
            $this->history->record($actor, $task, 'timer.started', ['time_entry_id' => $entry->id]);

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
            if (now()->lte($entry->started_at)) {
                throw ValidationException::withMessages(['timer' => 'Đợi ít nhất một giây trước khi dừng timer.']);
            }
            $entry->update(['ended_at' => now()]);
            $this->history->record($actor, $entry->task, 'timer.stopped', ['time_entry_id' => $entry->id]);
        }, 3);
    }

    public function entries(User $actor)
    {
        return TimeEntry::where('user_id', $actor->id)->with('task')->latest('started_at')->paginate(25);
    }

    public function adjustments(TimeEntry $entry)
    {
        return TimeEntryAdjustment::where('time_entry_id', $entry->id)->latest('created_at')->latest('id')->paginate(15);
    }

    public function value(TimeEntry $entry): array
    {
        return [
            'started_at' => $entry->started_at->utc()->format('Y-m-d H:i:s'),
            'ended_at' => $entry->ended_at?->utc()->format('Y-m-d H:i:s'),
            'note' => $entry->note,
        ];
    }

    public function version(TimeEntry $entry): string
    {
        return hash('sha256', json_encode([$this->value($entry), TimeEntryAdjustment::where('time_entry_id', $entry->id)->count()]));
    }

    private function local(string $input, string $zone): CarbonImmutable
    {
        return LocalTime::parse(substr($input, 0, 16), $zone, false)->addSeconds((int) substr($input, 17, 2));
    }

    public function saveAdjustment(User $actor, TimeEntry $entry, array $data): void
    {
        DB::transaction(function () use ($actor, $entry, $data) {
            User::lockForUpdate()->findOrFail($actor->id);
            Workspace::lockForUpdate()->findOrFail($entry->task->workspace_id);
            $entry = TimeEntry::lockForUpdate()->findOrFail($entry->id);
            Gate::forUser($actor)->authorize('adjust', $entry);
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
            if (TimeEntry::where('user_id', $actor->id)
                ->whereKeyNot($entry->id)
                ->where('started_at', '<', $end)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $start))
                ->exists()) {
                throw ValidationException::withMessages(['started_at' => 'Khoảng này trùng phiên giờ khác của bạn.']);
            }
            $before = $this->value($entry);
            $entry->fill([
                'started_at' => $start,
                'ended_at' => $end,
                'note' => $data['note'] ?? null,
            ]);
            if (! $entry->isDirty()) {
                return;
            }
            $entry->save();
            $after = $this->value($entry);
            $audit = TimeEntryAdjustment::create([
                'time_entry_id' => $entry->id,
                'actor_id' => $actor->id,
                'before' => $before,
                'after' => $after,
                'reason' => $data['reason'],
                'created_at' => now(),
            ]);
            $this->history->record($actor, $entry->task, 'timer.corrected', [
                'time_entry_id' => $entry->id,
                'adjustment_id' => $audit->id,
                'before' => $before,
                'after' => $after,
                'reason' => $data['reason'],
            ]);
        }, 3);
    }

    public function workHoursValues(User $user, ?string $timezone = null): array
    {
        $settings = $this->settings->stored($user);

        return $settings['work_hours'] ?? array_replace(config('work_schedule'), ['timezone' => $timezone ?? $user->timezone]);
    }

    public function workHoursVersion(User $user): string
    {
        return hash('sha256', json_encode($this->settings->stored($user)['work_hours'] ?? null));
    }

    /** @param array $data validated by WorkHoursRequest */
    public function saveWorkHours(User $user, array $data): void
    {
        usort($data['windows'], fn ($a, $b) => strcmp($a['start'], $b['start']));
        $end = '00:00';
        foreach ($data['windows'] as $window) {
            if ($window['start'] < $end || $window['end'] <= $window['start']) {
                throw ValidationException::withMessages(['windows' => 'Các ca làm phải kết thúc sau bắt đầu, cùng ngày và không chồng lấn.']);
            }
            $end = $window['end'];
        }
        DB::transaction(function () use ($user, $data) {
            User::lockForUpdate()->findOrFail($user->id);
            if (! hash_equals($this->workHoursVersion($user), $data['version'])) {
                throw ValidationException::withMessages(['version' => 'Giờ làm đã thay đổi. Tải lại trước khi lưu.']);
            }
            $settings = $this->settings->stored($user) ?: config('work_management.settings_defaults');
            $settings['work_hours'] = [
                'timezone' => $data['timezone'],
                'weekdays' => array_map('intval', $data['weekdays']),
                'windows' => $data['windows'],
                'slot_minutes' => (int) $data['slot_minutes'],
            ];
            $this->settings->store($user, $settings);
        }, 3);
    }
}
