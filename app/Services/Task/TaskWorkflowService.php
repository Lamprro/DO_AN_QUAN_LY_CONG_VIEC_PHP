<?php

namespace App\Services\Task;

use App\Authorization\TaskAccess;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskBlocker;
use App\Models\TaskDependency;
use App\Models\TaskWatcher;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskWorkflowService
{
    public function __construct(private TaskHistory $history, private TaskTreeService $tree, private TaskAccess $tasks, private TaskParticipants $participants) {}

    public function locked(User $actor, Task $task, string $ability, callable $action): mixed
    {
        return DB::transaction(function () use ($actor, $task, $ability, $action) {
            Workspace::lockForUpdate()->findOrFail($task->workspace_id);
            $task = Task::lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize($ability, $task);

            return $action($task);
        });
    }

    public function availability(User $actor, Task $task): array
    {
        if ($task->task_type->value === 'GROUP') {
            return ['actions' => [], 'notices' => ['Trạng thái nhóm được tổng hợp từ công việc con.']];
        }
        $graph = [
            'TODO' => ['IN_PROGRESS', 'CANCELLED'],
            'IN_PROGRESS' => ['REVIEW', 'CANCELLED'],
            'REVIEW' => ['DONE', 'IN_PROGRESS', 'CANCELLED'],
            'DONE' => ['TODO'],
            'CANCELLED' => ['TODO'],
        ];
        $actions = [];
        $from = $task->status->value;
        foreach ($graph[$from] ?? [] as $to) {
            $review = $from === 'REVIEW' && in_array($to, ['DONE', 'IN_PROGRESS'], true) || $from === 'DONE';
            if (Gate::forUser($actor)->denies($review ? 'review' : 'execute', $task)) {
                continue;
            }
            $reason = $this->blockingReason($actor, $task, $to);
            $label = match ($to) {
                'TODO' => 'Mở lại',
                'IN_PROGRESS' => $from === 'REVIEW' ? 'Yêu cầu sửa' : 'Bắt đầu thực hiện',
                'REVIEW' => 'Nộp duyệt',
                'DONE' => 'Duyệt hoàn thành',
                'CANCELLED' => 'Hủy công việc',
            };
            $actions[] = [
                'status' => $to,
                'label' => $label,
                'enabled' => $reason === null,
                'reason' => $reason,
                'needs_reason' => in_array($to, ['TODO', 'CANCELLED'], true) || $from === 'REVIEW' && $to === 'IN_PROGRESS',
            ];
        }

        return [
            'actions' => $actions,
            'notices' => $actions ? [] : ['Bạn không có quyền thực hiện bước tiếp theo hoặc phạm vi hiện không cho phép sửa.'],
        ];
    }

    public function options(User $actor, Task $task): array
    {
        return array_column(array_values(array_filter($this->availability($actor, $task)['actions'], fn ($a) => $a['enabled'])), 'status');
    }

    private function blockingReason(User $actor, Task $task, string $to): ?string
    {
        if (in_array($to, ['IN_PROGRESS', 'REVIEW', 'DONE'], true)) {
            if ($task->blockers()->where('status', 'open')->exists()) {
                return 'Cần giải quyết blocker đang mở trước.';
            }
            foreach ($task->incomingDependencies()->with('predecessor')->get() as $edge) {
                $pre = $edge->predecessor;
                if ($pre->status->value !== 'DONE' || ! $pre->completed_at || $pre->completed_at->addMinutes($edge->lag_minutes)->isFuture()) {
                    return 'Chờ '.$pre->code.' hoàn thành và đủ thời gian FS ('.$edge->lag_minutes.' phút).';
                }
            }
        }
        if ($to === 'REVIEW' && ! $task->reviewer_id && $task->workspace->owner_id !== $actor->id) {
            return 'Cần phân công người duyệt trước khi nộp duyệt.';
        }
        if (in_array($to, ['TODO', 'CANCELLED'], true) && $task->outgoingDependencies()->whereHas('successor', fn ($q) => $q->whereNotIn('status', ['TODO', 'CANCELLED']))->exists()) {
            return 'Task sau đã bắt đầu; xử lý dependency trước khi mở lại/hủy.';
        }
        if ($task->timeEntries()->whereNull('ended_at')->exists()) {
            return 'Hãy dừng timer đang chạy trước khi chuyển trạng thái.';
        }

        return null;
    }

    public function status(User $actor, Task $task, array $data): void
    {
        $this->locked($actor, $task, 'view', function ($task) use ($actor, $data) {
            $from = $task->status->value;
            $to = $data['status'];
            $reason = $data['reason'] ?? null;
            if (($data['expected_status'] ?? null) !== $from) {
                $this->fail('status', 'Trạng thái đã thay đổi; hãy tải lại trang trước khi thao tác.');
            }
            if ($task->task_type->value === 'GROUP') {
                $this->fail('status', 'Trạng thái GROUP được tổng hợp từ task con.');
            }
            if ($from === $to) {
                return;
            }
            $graph = [
                'TODO' => ['IN_PROGRESS', 'CANCELLED'],
                'IN_PROGRESS' => ['REVIEW', 'CANCELLED'],
                'REVIEW' => ['DONE', 'IN_PROGRESS', 'CANCELLED'],
                'DONE' => ['TODO'],
                'CANCELLED' => ['TODO'],
            ];
            if (! in_array($to, $graph[$from] ?? [], true)) {
                $this->fail('status', 'Chuyển trạng thái không hợp lệ.');
            }
            $review = $from === 'REVIEW' && in_array($to, ['DONE', 'IN_PROGRESS'], true) || $from === 'DONE';
            Gate::forUser($actor)->authorize($review ? 'review' : 'execute', $task);
            if (in_array($to, ['CANCELLED', 'TODO'], true) || $from === 'REVIEW' && $to === 'IN_PROGRESS') {
                if (! $reason) {
                    $this->fail('reason', 'Cần ghi lý do hủy, mở lại hoặc yêu cầu sửa.');
                }
            }
            if ($blocked = $this->blockingReason($actor, $task, $to)) {
                $this->fail('status', $blocked);
            }
            $values = ['status' => $to, 'completed_at' => $to === 'DONE' ? now() : null];
            $values['started_at'] = $to === 'TODO' ? null : $task->started_at ?? ($to === 'CANCELLED' ? null : now());
            $task->update($values);
            $this->history->record($actor, $task, 'task.status_changed', [
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ]);
            $this->tree->sync($actor, $task);
        });
    }

    public function dependency(User $actor, Task $task, array $data): void
    {
        $this->locked($actor, $task, 'link', function ($task) use ($actor, $data) {
            if ($task->task_type->value === 'GROUP' || $task->status->value !== 'TODO') {
                $this->fail('predecessor_task_id', 'Chỉ thêm dependency cho WORK/MILESTONE chưa bắt đầu.');
            }
            $pre = $this->tree->scope($task)->find($data['predecessor_task_id']);
            if (! $pre || $pre->archived_at || $pre->task_type->value === 'GROUP') {
                $this->fail('predecessor_task_id', 'Chọn WORK/MILESTONE chưa lưu trữ cùng phạm vi.');
            }
            if ($task->incomingDependencies()->where('predecessor_task_id', $pre->id)->exists()) {
                $this->fail('predecessor_task_id', 'Dependency đã tồn tại.');
            }
            TaskDependency::create([
                'predecessor_task_id' => $pre->id,
                'successor_task_id' => $task->id,
                'lag_minutes' => $data['lag_minutes'],
                'dependency_type' => 'FS',
            ]);
            $this->history->record($actor, $task, 'task.dependency_added', ['predecessor_id' => $pre->id, 'lag_minutes' => (int) $data['lag_minutes']]);
        });
    }

    public function removeDependency(User $actor, Task $task, string $id): void
    {
        $this->locked($actor, $task, 'link', function ($task) use ($actor, $id) {
            if ($task->status->value !== 'TODO') {
                $this->fail('dependency', 'Chỉ gỡ dependency khi task chưa bắt đầu.');
            }
            $edge = $task->incomingDependencies()->findOrFail($id);
            $data = $edge->only(['predecessor_task_id', 'lag_minutes']);
            $edge->delete();
            $this->history->record($actor, $task, 'task.dependency_removed', $data);
        });
    }

    public function blocker(User $actor, Task $task, array $data, ?string $id = null): void
    {
        $this->locked($actor, $task, 'block', function ($task) use ($actor, $data, $id) {
            if (in_array($task->status->value, ['DONE', 'CANCELLED'], true) || $task->task_type->value === 'GROUP') {
                $this->fail('reason', 'Chỉ ghi blocker cho công việc chưa đóng.');
            }
            if ($id) {
                $blocker = $task->blockers()->findOrFail($id);
                Gate::forUser($actor)->authorize('resolveBlocker', [$task, $blocker]);
                $from = $blocker->status->value;
                if ($from === $data['status']) {
                    return;
                }
                $blocker->update([
                    'status' => $data['status'],
                    'resolved_by' => $data['status'] === 'resolved' ? $actor->id : null,
                    'resolved_at' => $data['status'] === 'resolved' ? now() : null,
                ]);
                $event = 'task.blocker_updated';
            } else {
                $blocker = TaskBlocker::create([
                    'task_id' => $task->id,
                    'reported_by' => $actor->id,
                    'reason' => $data['reason'],
                    'status' => 'open',
                ]);
                $event = 'task.blocker_reported';
            }
            $this->history->record($actor, $task, $event, [
                'blocker_id' => $blocker->id,
                'status' => $blocker->status->value,
                'reason' => $data['reason'] ?? null,
            ]);
        });
    }

    public function comment(User $actor, Task $task, array $data, ?string $id = null, bool $delete = false): void
    {
        $this->locked($actor, $task, 'comment', function ($task) use ($actor, $data, $id, $delete) {
            $mentions = ! $id && ! $delete ? $this->participants->mentions($task, $data['mentions'] ?? []) : [];
            if ($id) {
                $activity = $task->activities()->where('type', 'COMMENT')->findOrFail($id);
                abort_unless($activity->actor_id === $actor->id || $delete && $this->tasks->scope($actor, $task->workspace, $task->project, 'task.assign'), 403);
                if ($delete) {
                    $activity->delete();
                } else {
                    $activity->update(['content' => $data['content']]);
                }
                $event = $delete ? 'task.comment_deleted' : 'task.comment_updated';
            } else {
                $activity = TaskActivity::create([
                    'task_id' => $task->id,
                    'actor_id' => $actor->id,
                    'type' => 'COMMENT',
                    'source' => 'user',
                    'content' => $data['content'],
                    'data' => ['mentions' => $mentions],
                ]);
                $event = 'task.comment_added';
            }
            $this->history->record($actor, $task, $event, ['activity_id' => $activity->id, 'mentions' => $mentions]);
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function collaborationData(Task $task): array
    {
        $people = $this->participants->people($task);
        $ready = Schema::hasTable('task_watchers');
        $watchers = $ready ? TaskWatcher::where('task_id', $task->id)->with('user')->get()->filter(fn ($w) => $people->contains('user_id', $w->user_id)) : collect();

        return [
            'collaborationReady' => $ready,
            'collaborationPeople' => $people,
            'watchers' => $watchers,
        ];
    }

    public function watch(User $actor, Task $task, string $userId, bool $remove = false): void
    {
        DB::transaction(function () use ($actor, $task, $userId, $remove) {
            Workspace::lockForUpdate()->findOrFail($task->workspace_id);
            $task = Task::lockForUpdate()->findOrFail($task->id);
            abort_unless(Gate::forUser($actor)->allows('view', $task), 403);
            abort_unless($actor->id === $userId || Gate::forUser($actor)->allows('link', $task), 403);
            if (! $remove) {
                if ($task->archived_at || ! $this->tasks->projectWritable($task->project)) {
                    throw ValidationException::withMessages(['watcher' => 'Không thêm theo dõi vào dữ liệu đã lưu trữ.']);
                }
                $this->participants->mentions($task, [$userId]);
                if (TaskWatcher::where('task_id', $task->id)->count() >= 100 && ! TaskWatcher::where('task_id', $task->id)->where('user_id', $userId)->exists()) {
                    throw ValidationException::withMessages(['watcher' => 'Tối đa 100 người theo dõi mỗi task.']);
                }
                $watcher = TaskWatcher::firstOrCreate(['task_id' => $task->id, 'user_id' => $userId], ['added_by' => $actor->id]);
                $changed = $watcher->wasRecentlyCreated;
            } else {
                $changed = TaskWatcher::where('task_id', $task->id)->where('user_id', $userId)->delete() > 0;
            }
            if ($changed) {
                $this->history->record($actor, $task, $remove ? 'task.watcher_removed' : 'task.watcher_added', ['user_id' => $userId]);
            }
        }, 3);
    }

    public function storeAttachment(User $actor, Task $task, UploadedFile $file): void
    {
        Gate::forUser($actor)->authorize('attach', $task);
        $originalName = mb_convert_encoding($file->getClientOriginalName(), 'UTF-8', 'UTF-8');
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace('\\', '/', $originalName));
        $name = mb_substr(basename($name), 0, 180);
        $path = $file->storeAs($task->id, Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'task_files');
        try {
            $this->locked($actor, $task, 'attach', function ($task) use ($actor, $file, $name, $path) {
                $attachment = TaskActivity::create([
                    'task_id' => $task->id,
                    'actor_id' => $actor->id,
                    'type' => 'ATTACHMENT',
                    'source' => 'user',
                    'content' => $name,
                    'data' => [
                        'disk' => 'task_files',
                        'path' => $path,
                        'original_name' => $name,
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'sha256' => hash_file('sha256', $file->getRealPath()),
                    ],
                ]);
                $this->history->record($actor, $task, 'task.file_uploaded', [
                    'activity_id' => $attachment->id,
                    'name' => $name,
                    'size' => $file->getSize(),
                ]);
            });
        } catch (Throwable $error) {
            Storage::disk('task_files')->delete($path);
            throw $error;
        }
    }

    public function downloadAttachment(User $actor, Task $task, string $id)
    {
        Gate::forUser($actor)->authorize('view', $task);
        $attachment = $task->activities()->where('type', 'ATTACHMENT')->findOrFail($id);
        $data = $attachment->data;
        abort_unless(($data['disk'] ?? null) === 'task_files' && str_starts_with($data['path'] ?? '', $task->id.'/') && ! str_contains($data['path'], '..'), 404);
        abort_unless(Storage::disk('task_files')->exists($data['path']), 404);

        return Storage::disk('task_files')->download($data['path'], $data['original_name'], ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function removeAttachment(User $actor, Task $task, string $id): void
    {
        $this->locked($actor, $task, 'attach', function ($task) use ($actor, $id) {
            $attachment = $task->activities()->where('type', 'ATTACHMENT')->findOrFail($id);
            abort_unless($attachment->actor_id === $actor->id || $this->tasks->scope($actor, $task->workspace, $task->project, 'task.assign'), 403);
            $attachment->delete();
            $this->history->record($actor, $task, 'task.file_removed', ['activity_id' => $id]);
        });
    }
}
