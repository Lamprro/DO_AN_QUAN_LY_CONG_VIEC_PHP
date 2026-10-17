<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TaskTreeService
{
    public function __construct(private TaskHistory $history) {}

    public function scope(Task $task)
    {
        return Task::where('workspace_id', $task->workspace_id)->where('project_id', $task->project_id);
    }

    public function validate(Task $task, array $values): void
    {
        $type = $values['task_type'] ?? $task->task_type?->value ?? 'WORK';
        $parentId = array_key_exists('parent_task_id', $values) ? $values['parent_task_id'] : $task->parent_task_id;
        $estimate = array_key_exists('estimate_minutes', $values) ? $values['estimate_minutes'] : $task->estimate_minutes;
        if ($type !== 'WORK' && $estimate !== null) {
            $this->fail('estimate_minutes', 'Chỉ WORK lá có ước lượng; hãy để trống cho GROUP/MILESTONE.');
        }
        if ($type === 'GROUP' && ((array_key_exists('assignee_id', $values) ? $values['assignee_id'] : $task->assignee_id) || (array_key_exists('reviewer_id', $values) ? $values['reviewer_id'] : $task->reviewer_id))) {
            $this->fail('task_type', 'GROUP không phân công trực tiếp; hãy phân công các WORK lá.');
        }
        $changed = $task->exists && (($type !== $task->task_type->value) || $parentId !== $task->parent_task_id);
        if ($changed && ($task->status->value !== 'TODO' || $task->started_at)) {
            $this->fail('parent_task_id', 'Chỉ thay đổi cấu trúc của task chưa bắt đầu.');
        }
        if ($task->exists && $type !== $task->task_type->value) {
            if ($task->children()->exists() || $task->estimate_minutes !== null || $task->assignee_id || $task->reviewer_id) {
                $this->fail('task_type', 'Không chuyển loại khi còn task con, estimate hoặc phân công.');
            }
            foreach (['incomingDependencies', 'outgoingDependencies', 'calendarEvents', 'timeEntries', 'blockers'] as $relation) {
                if ($task->$relation()->exists()) {
                    $this->fail('task_type', 'Task đã có dữ liệu nghiệp vụ, không thể chuyển loại.');
                }
            }
            if ($task->activities()->whereIn('type', ['COMMENT', 'ATTACHMENT'])->exists()) {
                $this->fail('task_type', 'Task đã có trao đổi hoặc file, không thể chuyển loại.');
            }
        }
        $all = $this->scope($task)->get()->keyBy('id');
        $seen = [$task->id];
        $depth = 1;
        $cursor = $parentId;
        while ($cursor) {
            if (in_array($cursor, $seen, true)) {
                $this->fail('parent_task_id', 'Không được tạo chu trình cha/con.');
            }
            $parent = $all->get($cursor);
            if (! $parent || $parent->task_type->value !== 'GROUP' || $parent->archived_at) {
                $this->fail('parent_task_id', 'Cha phải là GROUP chưa lưu trữ, cùng workspace/project.');
            }
            $seen[] = $cursor;
            $depth++;
            $cursor = $parent->parent_task_id;
        }
        $height = function ($id, $visited = []) use (&$height, $all): int {
            if (in_array($id, $visited, true)) {
                return 10000;
            }
            $visited[] = $id;
            $max = 1;
            foreach ($all->where('parent_task_id', $id) as $child) {
                $max = max($max, 1 + $height($child->id, $visited));
            }

            return $max;
        };
        if ($depth + ($task->exists ? $height($task->id) - 1 : 0) > config('tasks.max_depth')) {
            $this->fail('parent_task_id', 'Cây công việc tối đa '.config('tasks.max_depth').' cấp.');
        }
    }

    public function sync(User $actor, Task $task): void
    {
        $all = $this->scope($task)->get()->keyBy('id');
        $depth = function ($item) use ($all) {
            $n = 0;
            $seen = [];
            while ($item->parent_task_id && isset($all[$item->parent_task_id]) && ! isset($seen[$item->id])) {
                $seen[$item->id] = true;
                $n++;
                $item = $all[$item->parent_task_id];
            }

            return $n;
        };
        foreach ($all->filter(fn ($t) => $t->task_type->value === 'GROUP')->sortByDesc($depth) as $group) {
            $children = $all->where('parent_task_id', $group->id);
            $statuses = $children->map(fn ($t) => $t->status->value);
            $state = 'TODO';
            if ($children->isNotEmpty()) {
                if ($statuses->every(fn ($s) => in_array($s, ['DONE', 'CANCELLED'], true))) {
                    $state = $statuses->contains('DONE') ? 'DONE' : 'CANCELLED';
                } elseif ($statuses->contains(fn ($s) => $s !== 'TODO')) {
                    $state = 'IN_PROGRESS';
                }
            }
            if ($group->status->value !== $state) {
                $old = $group->status->value;
                $group->update(['status' => $state, 'started_at' => $state !== 'TODO' ? ($group->started_at ?? now()) : null, 'completed_at' => $state === 'DONE' ? now() : null]);
                $this->history->record($actor, $group, 'task.group_synced', ['from' => $old, 'to' => $state]);
            }
        }
    }

    public function progress(Collection $tasks): array
    {
        $leaves = $tasks->filter(fn ($t) => $t->task_type->value === 'WORK' && $t->status->value !== 'CANCELLED');
        $weighted = $leaves->isNotEmpty() && $leaves->every(fn ($t) => $t->estimate_minutes > 0);
        $total = $weighted ? $leaves->sum('estimate_minutes') : $leaves->count();
        $done = $weighted ? $leaves->filter(fn ($t) => $t->status->value === 'DONE')->sum('estimate_minutes') : $leaves->filter(fn ($t) => $t->status->value === 'DONE')->count();

        return ['method' => $weighted ? 'estimate' : 'count', 'total' => $total, 'done' => $done, 'percent' => $total ? round(100 * $done / $total, 1) : 0, 'work_count' => $leaves->count()];
    }

    public function descendants(Task $task, Collection $all): Collection
    {
        $ids = [$task->id];
        $queue = [$task->id];
        while ($queue) {
            $parent = array_pop($queue);
            foreach ($all->where('parent_task_id', $parent) as $child) {
                if (! in_array($child->id, $ids, true)) {
                    $ids[] = $child->id;
                    $queue[] = $child->id;
                }
            }
        }

        return $all->whereIn('id', $ids)->values();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
