<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskWatcher;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Support\ProjectAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TaskCollaborationService
{
    public function people(Task $task)
    {
        return app(ProjectAccess::class)->eligibleMembers($task->workspace, $task->project)->filter(fn ($m) => app(TaskPolicy::class)->view($m->user, $task));
    }

    public function viewData(Task $task): array
    {
        $people = $this->people($task);
        $ready = Schema::hasTable('task_watchers');
        $watchers = $ready ? TaskWatcher::where('task_id', $task->id)->with('user')->get()->filter(fn ($w) => $people->contains('user_id', $w->user_id)) : collect();

        return ['collaborationReady' => $ready, 'collaborationPeople' => $people, 'watchers' => $watchers];
    }

    public function mentions(Task $task, array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if (count($ids) > 20 || array_diff($ids, $this->people($task)->pluck('user_id')->all())) {
            throw ValidationException::withMessages(['mentions' => 'Chỉ nhắc tên tối đa20 thành viên đang có quyền xem task.']);
        }

        return $ids;
    }

    public function watch(User $actor, Task $task, string $userId, bool $remove = false): void
    {
        DB::transaction(function () use ($actor, $task, $userId, $remove) {
            Workspace::lockForUpdate()->findOrFail($task->workspace_id);
            $task = Task::lockForUpdate()->findOrFail($task->id);
            abort_unless(app(TaskPolicy::class)->view($actor, $task), 403);
            abort_unless($actor->id === $userId || app(TaskPolicy::class)->link($actor, $task), 403);
            if (! $remove) {
                if ($task->archived_at || ! app(TaskPolicy::class)->projectWritable($task->project)) {
                    throw ValidationException::withMessages(['watcher' => 'Không thêm theo dõi vào dữ liệu đã lưu trữ.']);
                }
                $this->mentions($task, [$userId]);
                if (TaskWatcher::where('task_id', $task->id)->count() >= 100 && ! TaskWatcher::where('task_id', $task->id)->where('user_id', $userId)->exists()) {
                    throw ValidationException::withMessages(['watcher' => 'Tối đa100 người theo dõi mỗi task.']);
                }
                $watcher = TaskWatcher::firstOrCreate(['task_id' => $task->id, 'user_id' => $userId], ['added_by' => $actor->id]);
                $changed = $watcher->wasRecentlyCreated;
            } else {
                $changed = TaskWatcher::where('task_id', $task->id)->where('user_id', $userId)->delete() > 0;
            }
            if ($changed) {
                app(TaskHistory::class)->record($actor, $task, $remove ? 'task.watcher_removed' : 'task.watcher_added', ['user_id' => $userId]);
            }
        }, 3);
    }
}
