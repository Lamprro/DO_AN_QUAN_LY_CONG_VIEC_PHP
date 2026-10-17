<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskBlocker;
use App\Models\TaskDependency;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskWorkflowService
{
    public function __construct(private TaskHistory $history, private TaskTreeService $tree, private TaskPolicy $policy) {}

    public function locked(User $actor, Task $task, string $ability, callable $action): mixed
    {
        return DB::transaction(function () use ($actor, $task, $ability, $action) {
            Workspace::lockForUpdate()->findOrFail($task->workspace_id);
            $task = Task::lockForUpdate()->findOrFail($task->id);
            Gate::forUser($actor)->authorize($ability, $task);

            return $action($task);
        });
    }

    public function options(User $actor, Task $task): array
    {
        if ($task->task_type->value === 'GROUP') {
            return [];
        }
        $graph = ['TODO' => ['IN_PROGRESS', 'CANCELLED'], 'IN_PROGRESS' => ['REVIEW', 'CANCELLED'], 'REVIEW' => ['DONE', 'IN_PROGRESS', 'CANCELLED'], 'DONE' => ['TODO'], 'CANCELLED' => ['TODO']];
        $from = $task->status->value;

        return array_values(array_filter($graph[$from] ?? [], fn ($to) => (($from === 'REVIEW' && in_array($to, ['DONE', 'IN_PROGRESS'], true)) || $from === 'DONE') ? $this->policy->review($actor, $task) : $this->policy->execute($actor, $task)));
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
            $graph = ['TODO' => ['IN_PROGRESS', 'CANCELLED'], 'IN_PROGRESS' => ['REVIEW', 'CANCELLED'], 'REVIEW' => ['DONE', 'IN_PROGRESS', 'CANCELLED'], 'DONE' => ['TODO'], 'CANCELLED' => ['TODO']];
            if (! in_array($to, $graph[$from] ?? [], true)) {
                $this->fail('status', 'Chuyển trạng thái không hợp lệ.');
            }
            $review = ($from === 'REVIEW' && in_array($to, ['DONE', 'IN_PROGRESS'], true)) || $from === 'DONE';
            Gate::forUser($actor)->authorize($review ? 'review' : 'execute', $task);
            if (in_array($to, ['CANCELLED', 'TODO'], true) || ($from === 'REVIEW' && $to === 'IN_PROGRESS')) {
                if (! $reason) {
                    $this->fail('reason', 'Cần ghi lý do hủy, mở lại hoặc yêu cầu sửa.');
                }
            }
            if (in_array($to, ['IN_PROGRESS', 'REVIEW', 'DONE'], true)) {
                if ($task->blockers()->where('status', 'open')->exists()) {
                    $this->fail('status', 'Cần giải quyết blocker đang mở trước.');
                }
                foreach ($task->incomingDependencies()->with('predecessor')->get() as $edge) {
                    $pre = $edge->predecessor;
                    if ($pre->status->value !== 'DONE' || ! $pre->completed_at || $pre->completed_at->addMinutes($edge->lag_minutes)->isFuture()) {
                        $this->fail('status', 'Dependency chưa hoàn tất hoặc chưa đủ thời gian chờ FS.');
                    }
                }
            }
            if ($to === 'REVIEW' && ! $task->reviewer_id && $task->workspace->owner_id !== $actor->id) {
                $this->fail('status', 'Cần phân công người duyệt trước khi nộp duyệt.');
            }
            if (in_array($to, ['TODO', 'CANCELLED'], true) && $task->outgoingDependencies()->whereHas('successor', fn ($q) => $q->whereNotIn('status', ['TODO', 'CANCELLED']))->exists()) {
                $this->fail('status', 'Task sau đã bắt đầu; cần xử lý dependency trước khi mở lại/hủy.');
            }
            if ($task->timeEntries()->whereNull('ended_at')->exists()) {
                $this->fail('status', 'Hãy kết thúc timer đang chạy trước khi chuyển trạng thái.');
            }
            $values = ['status' => $to, 'completed_at' => $to === 'DONE' ? now() : null];
            $values['started_at'] = $to === 'TODO' ? null : ($task->started_at ?? ($to === 'CANCELLED' ? null : now()));
            $task->update($values);
            $this->history->record($actor, $task, 'task.status_changed', ['from' => $from, 'to' => $to, 'reason' => $reason]);
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
            TaskDependency::create(['predecessor_task_id' => $pre->id, 'successor_task_id' => $task->id, 'lag_minutes' => $data['lag_minutes'], 'dependency_type' => 'FS']);
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
                $blocker->update(['status' => $data['status'], 'resolved_by' => $data['status'] === 'resolved' ? $actor->id : null, 'resolved_at' => $data['status'] === 'resolved' ? now() : null]);
                $event = 'task.blocker_updated';
            } else {
                $blocker = TaskBlocker::create(['task_id' => $task->id, 'reported_by' => $actor->id, 'reason' => $data['reason'], 'status' => 'open']);
                $event = 'task.blocker_reported';
            }
            $this->history->record($actor, $task, $event, ['blocker_id' => $blocker->id, 'status' => $blocker->status->value, 'reason' => $data['reason'] ?? null]);
        });
    }

    public function comment(User $actor, Task $task, array $data, ?string $id = null, bool $delete = false): void
    {
        $this->locked($actor, $task, 'comment', function ($task) use ($actor, $data, $id, $delete) {
            if ($id) {
                $activity = $task->activities()->where('type', 'COMMENT')->findOrFail($id);
                abort_unless($activity->actor_id === $actor->id || ($delete && $this->policy->scope($actor, $task->workspace, $task->project, 'task.assign')), 403);
                if ($delete) {
                    $activity->delete();
                } else {
                    $activity->update(['content' => $data['content']]);
                }
                $event = $delete ? 'task.comment_deleted' : 'task.comment_updated';
            } else {
                $activity = TaskActivity::create(['task_id' => $task->id, 'actor_id' => $actor->id, 'type' => 'COMMENT', 'source' => 'user', 'content' => $data['content']]);
                $event = 'task.comment_added';
            }
            $this->history->record($actor, $task, $event, ['activity_id' => $activity->id]);
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
