<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Support\EditRevision;
use App\Support\ProjectAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(private ProjectAccess $access, private TaskPolicy $policy, private TaskTreeService $tree, private ?TaskHistory $history = null) {}

    public function save(User $actor, Workspace $workspace, ?Project $project, array $data, ?Task $task = null): Task
    {
        try {
            return DB::transaction(function () use ($actor, $workspace, $project, $data, $task) {
                $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
                if ($project) {
                    $project = $workspace->projects()->lockForUpdate()->findOrFail($project->id);
                }
                if ($task) {
                    $task = $workspace->tasks()->where('project_id', $project?->id)->lockForUpdate()->findOrFail($task->id);
                    Gate::forUser($actor)->authorize('update', $task);
                    EditRevision::check($task, $data);
                } else {
                    abort_unless($this->policy->projectWritable($project) && $this->policy->scope($actor, $workspace, $project, 'task.create'), 403);
                }
                $values = array_intersect_key($data, array_flip(['code', 'title', 'description', 'priority', 'estimate_minutes', 'deadline_at', 'assignee_id', 'reviewer_id', 'task_type', 'parent_task_id', 'group_label', 'sort_order']));
                if (empty($values['code'])) {
                    $values['code'] = $task?->code ?? 'T-'.Str::ulid();
                }
                if ($workspace->tasks()->where('code', $values['code'])->when($task, fn ($q) => $q->whereKeyNot($task->id))->exists()) {
                    throw ValidationException::withMessages(['code' => 'Mã task đã có trong workspace.']);
                }
                if (array_key_exists('deadline_at', $values) && $values['deadline_at']) {
                    $values['deadline_at'] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $values['deadline_at'], $workspace->timezone)->utc();
                }
                foreach (['assignee_id', 'reviewer_id'] as $field) {
                    if (! array_key_exists($field, $values)) {
                        continue;
                    }
                    if ($values[$field] !== $task?->getAttribute($field)) {
                        abort_unless($this->policy->scope($actor, $workspace, $project, 'task.assign'), 403);
                        if ($values[$field] && ! $this->access->eligibleMembers($workspace, $project, $field === 'reviewer_id' ? 'task.review' : 'task.view')->contains('user_id', $values[$field])) {
                            throw ValidationException::withMessages([$field => 'Chọn người đang hoạt động và có quyền xem task trong phạm vi này.']);
                        }
                    }
                }
                $probe = $task ?? new Task(['workspace_id' => $workspace->id, 'project_id' => $project?->id]);
                $this->tree->validate($probe, $values);
                if ($task) {
                    $before = $task->getRawOriginal();
                    $task->fill($values);
                    $deadlineChanged = $task->isDirty('deadline_at');
                    if ($deadlineChanged && empty(trim($data['deadline_reason'] ?? ''))) {
                        throw ValidationException::withMessages(['deadline_reason' => 'Cần ghi lý do thay đổi hạn.']);
                    }
                    $changed = $task->isDirty();
                    $task->save();
                    if ($deadlineChanged) {
                        $this->history->record($actor, $task, 'task.deadline_changed', ['before' => ['deadline_at' => $before['deadline_at'] ?? null], 'after' => ['deadline_at' => $task->getRawOriginal('deadline_at')], 'reason' => $data['deadline_reason']]);
                    }
                    if ($changed) {
                        $this->history->record($actor, $task, 'task.updated', ['before' => array_intersect_key($before, $values), 'after' => array_intersect_key($task->getAttributes(), $values)]);
                    }
                } else {
                    $task = Task::create($values + ['workspace_id' => $workspace->id, 'project_id' => $project?->id, 'created_by' => $actor->id, 'task_type' => 'WORK', 'status' => 'TODO']);
                    $this->history->record($actor, $task, 'task.created', ['code' => $task->code, 'task_type' => $task->task_type->value]);
                }
                $this->tree->sync($actor, $task);

                return $task;
            });
        } catch (UniqueConstraintViolationException $error) {
            throw ValidationException::withMessages(['code' => 'Mã task đã tồn tại. Hãy nhập mã khác hoặc để trống để tạo mã tự động.']);
        }
    }

    public function archive(User $actor, Workspace $workspace, ?Project $project, Task $task, bool $restore = false): void
    {
        DB::transaction(function () use ($actor, $workspace, $project, $task, $restore) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $task = $workspace->tasks()->where('project_id', $project?->id)->lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize('archive', $task);
            if (! $restore && $task->children()->whereNull('archived_at')->exists()) {
                throw ValidationException::withMessages(['task' => 'Hãy lưu trữ task con trước khi lưu trữ GROUP.']);
            }
            if ((bool) $task->archived_at === ! $restore) {
                return;
            }
            $task->update(['archived_at' => $restore ? null : now()]);
            $this->history->record($actor, $task, $restore ? 'task.restored' : 'task.archived');
        });
    }

    public function delete(User $actor, Workspace $workspace, ?Project $project, Task $task): void
    {
        DB::transaction(function () use ($actor, $workspace, $project, $task) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $task = $workspace->tasks()->where('project_id', $project?->id)->lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize('delete', $task);
            if ($task->status->value !== 'TODO' || $task->started_at || $task->completed_at) {
                throw ValidationException::withMessages(['task' => 'Task đã hoạt động; hãy dùng lưu trữ.']);
            }
            foreach (['children', 'blockers', 'incomingDependencies', 'outgoingDependencies', 'calendarEvents', 'timeEntries'] as $relation) {
                if ($task->$relation()->exists()) {
                    throw ValidationException::withMessages(['task' => 'Task có dữ liệu liên quan; hãy dùng lưu trữ.']);
                }
            }
            if ($task->activities()->withTrashed()->exists() || DB::table('github_logs')->where('task_id', $task->id)->exists() || DB::table('notifications')->where('task_id', $task->id)->exists()) {
                throw ValidationException::withMessages(['task' => 'Task có lịch sử; hãy dùng lưu trữ.']);
            }
            $task->delete();
            $this->tree->sync($actor, $task);
        });
    }

    public function recordHistory(User $actor, Task $task, string $event, array $data = []): TaskActivity
    {
        $activity = TaskActivity::create([
            'task_id' => $task->id,
            'actor_id' => $actor->id,
            'type' => 'HISTORY',
            'source' => 'user',
            'event_type' => $event,
            'data' => $data,
        ]);

        app(NotificationService::class)->taskEvent($activity, $task, $actor, $data);

        return $activity;
    }

    public function presets(Workspace $workspace): array
    {
        return $this->presetsForDate(CarbonImmutable::now($workspace->timezone));
    }

    public function presetsForDate(CarbonImmutable $date): array
    {
        $day = $date->startOfDay();
        $friday = (5 - $day->isoWeekday() + 7) % 7;

        return [
            ['key' => 'today', 'label' => 'Hôm nay 17:30', 'value' => $day->setTime(17, 30)->format('Y-m-d\TH:i')],
            ['key' => 'tomorrow', 'label' => 'Ngày mai 17:30', 'value' => $day->addDay()->setTime(17, 30)->format('Y-m-d\TH:i')],
            ['key' => 'friday', 'label' => $friday === 0 ? 'Hôm nay (Thứ Sáu)' : ($day->isoWeekday() < 5 ? 'Thứ Sáu tuần này' : 'Thứ Sáu tuần sau'), 'value' => $day->addDays($friday)->setTime(17, 30)->format('Y-m-d\TH:i')],
            ['key' => 'week', 'label' => '+7 ngày', 'value' => $day->addDays(7)->setTime(17, 30)->format('Y-m-d\TH:i')],
        ];
    }

    public function deadlineInsight(Task $task): array
    {
        $overdue = $task->task_type->value === 'WORK' && ! $task->archived_at && ! in_array($task->status->value, ['DONE', 'CANCELLED'], true) && $task->deadline_at?->isPast();
        $conditions = [];
        if ($task->blockers()->where('status', 'open')->exists()) {
            $conditions[] = 'Có blocker đang mở';
        }
        foreach ($task->incomingDependencies()->with('predecessor')->get() as $edge) {
            $pre = $edge->predecessor;
            if ($pre->status->value !== 'DONE' || ! $pre->completed_at || $pre->completed_at->addMinutes($edge->lag_minutes)->isFuture()) {
                $conditions[] = 'Đang chờ '.$pre->code.' hoàn tất và đủ FS/lag';
            }
        }
        if ($task->status->value === 'REVIEW') {
            $conditions[] = 'Đang chờ duyệt';
        }
        if (! $task->assignee_id) {
            $conditions[] = 'Chưa phân công';
        }

        return [
            'overdue' => (bool) $overdue,
            'conditions' => $conditions,
            'history' => $task->activities()->with('actor')->where('event_type', 'task.deadline_changed')->latest('created_at')->latest('id')->limit(20)->get(),
        ];
    }
}
