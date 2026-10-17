<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Support\ProjectAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(private ProjectAccess $access, private TaskPolicy $policy, private TaskTreeService $tree, private TaskHistory $history) {}

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
                    $changed = $task->isDirty();
                    $task->save();
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
}
