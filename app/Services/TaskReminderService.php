<?php

namespace App\Services;

use App\Jobs\SendTaskReminder;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

class TaskReminderService
{
    public function source(Task $task, string $kind): string
    {
        $value = $kind === 'due' ? $task->deadline_at?->toIso8601String() : ($task->activities()->where('event_type', 'task.status_changed')->where('data->to', 'REVIEW')->latest('created_at')->latest('id')->value('id') ?? $task->created_at->toIso8601String());

        return hash('sha256', (string) $value);
    }

    public function eligible(User $user, Task $task, string $kind, int $reviewAfter = 60): bool
    {
        if ($user->status->value !== 'active' || ! $user->can('view', $task) || ! app(SettingService::class)->values($user)['notifications']['in_app'] || $task->archived_at || $task->task_type->value !== 'WORK' || in_array($task->status->value, ['DONE', 'CANCELLED'], true) || ! app(TaskPolicy::class)->projectWritable($task->project)) {
            return false;
        }
        if ($kind === 'due') {
            return $task->assignee_id === $user->id && $task->deadline_at && $task->deadline_at->lte(now()->addDay());
        }
        if ($kind !== 'review' || $task->status->value !== 'REVIEW' || ! $user->can('review', $task) || ($task->reviewer_id !== $user->id && ! (! $task->reviewer_id && $task->workspace->owner_id === $user->id))) {
            return false;
        }
        $entered = $task->activities()->where('event_type', 'task.status_changed')->where('data->to', 'REVIEW')->latest('created_at')->value('created_at') ?? $task->created_at;

        return CarbonImmutable::parse($entered)->lte(now()->subMinutes($reviewAfter));
    }

    public function scan(User $user, ?Workspace $workspace = null, int $reviewAfter = 60): int
    {
        $queueConnection = config('queue.connections.database.connection');
        if ($queueConnection && $queueConnection !== config('database.default')) {
            throw ValidationException::withMessages(['queue' => 'Nhắc việc cần queue database cùng connection mặc định để lưu ledger/job nguyên tử.']);
        }
        $count = 0;
        Task::where('task_type', 'WORK')->whereNull('archived_at')->whereNotIn('status', ['DONE', 'CANCELLED'])->when($workspace, fn ($q) => $q->where('workspace_id', $workspace->id))->where(function ($q) use ($user) {
            $q->where('assignee_id', $user->id)->orWhere('reviewer_id', $user->id)->orWhere(fn ($q) => $q->whereNull('reviewer_id')->whereHas('workspace', fn ($w) => $w->where('owner_id', $user->id)));
        })->orderBy('id')->chunkById(100, function ($tasks) use ($user, $reviewAfter, &$count) {
            foreach ($tasks as $task) {
                foreach (['due', 'review'] as $kind) {
                    $created = DB::transaction(function () use ($user, $task, $kind, $reviewAfter) {
                        $user = User::lockForUpdate()->findOrFail($user->id);
                        Workspace::lockForUpdate()->findOrFail($task->workspace_id);
                        $task = Task::findOrFail($task->id);
                        if (! $this->eligible($user->fresh(), $task, $kind, $reviewAfter)) {
                            return;
                        }$date = now($user->timezone)->toDateString();
                        $token = $this->source($task, $kind);
                        $key = hash('sha256', $user->id.'|'.$task->id.'|'.$kind.'|'.$date.'|'.$user->timezone.'|'.$token);
                        $reminder = TaskReminder::firstOrCreate(['dedupe_key' => $key], ['workspace_id' => $task->workspace_id, 'task_id' => $task->id, 'user_id' => $user->id, 'kind' => $kind, 'local_date' => $date, 'timezone' => $user->timezone, 'source_token' => $token, 'review_after_minutes' => $reviewAfter, 'status' => 'queued']);
                        if ($reminder->wasRecentlyCreated) {
                            Queue::connection('database')->pushOn('work-management-reminders', new SendTaskReminder($reminder->id));

                            return true;
                        }

                        return false;
                    }, 3);
                    if ($created) {
                        $count++;
                    }
                }
            }
        });

        return $count;
    }

    public function deliver(string $id): void
    {
        $seed = TaskReminder::findOrFail($id);
        DB::transaction(function () use ($id, $seed) {
            $user = User::lockForUpdate()->findOrFail($seed->user_id);
            Workspace::lockForUpdate()->findOrFail($seed->workspace_id);
            $r = TaskReminder::lockForUpdate()->findOrFail($id);
            if ($r->status !== 'queued') {
                return;
            }$task = Task::lockForUpdate()->findOrFail($r->task_id);
            if ($r->timezone !== $user->timezone || $r->local_date->toDateString() !== now($user->timezone)->toDateString() || $r->source_token !== $this->source($task, $r->kind) || ! $this->eligible($user, $task, $r->kind, $r->review_after_minutes)) {
                $r->update(['status' => 'skipped', 'skip_reason' => 'Nguồn, quyền, ngày địa phương hoặc lựa chọn nhận nhắc không còn phù hợp.']);

                return;
            }
            Notification::firstOrCreate(['dedupe_key' => 'reminder:'.$r->dedupe_key], ['recipient_id' => $user->id, 'workspace_id' => $task->workspace_id, 'project_id' => $task->project_id, 'task_id' => $task->id, 'type' => 'task.reminder_'.$r->kind, 'title' => $r->kind === 'review' ? 'Nhắc công việc chờ bạn duyệt' : ($task->deadline_at->isPast() ? 'Nhắc công việc quá hạn' : 'Nhắc hạn trong24 giờ'), 'body' => $task->code.' · '.$task->title, 'deliveries' => [['channel' => 'in_app', 'status' => 'sent', 'attempts' => 1, 'sent_at' => now()->toIso8601String()]]]);
            $r->update(['status' => 'sent', 'sent_at' => now()]);
        }, 3);
    }
}
