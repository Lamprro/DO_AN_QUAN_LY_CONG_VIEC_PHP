<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SavedTaskFilter;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskWatcher;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Support\EditRevision;
use App\Support\ProjectAccess;
use App\Support\TaskFilters;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskUtilityService
{
    public function saveFilter(User $actor, Workspace $workspace, ?Project $project, string $name, array $query): SavedTaskFilter
    {
        return DB::transaction(function () use ($actor, $workspace, $project, $name, $query) {
            User::lockForUpdate()->findOrFail($actor->id);
            self::checkScope($actor, $workspace, $project);
            $q = SavedTaskFilter::where('user_id', $actor->id)->where('workspace_id', $workspace->id)->where('project_id', $project?->id);
            if ((clone $q)->count() >= 20 || (clone $q)->where('name', $name)->exists()) {
                throw ValidationException::withMessages(['name' => 'Tối đa 20 bộ lọc trong phạm vi; tên không được trùng.']);
            }

            return SavedTaskFilter::create(['user_id' => $actor->id, 'workspace_id' => $workspace->id, 'project_id' => $project?->id, 'name' => $name, 'query' => TaskFilters::clean($query)]);
        }, 3);
    }

    public function bulk(User $actor, Workspace $workspace, ?Project $project, array $data): int
    {
        return DB::transaction(function () use ($actor, $workspace, $project, $data) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            self::checkScope($actor, $workspace, $project);
            $items = $data['items'];
            if ($data['action'] === 'assign') {
                abort_unless(app(TaskPolicy::class)->scope($actor, $workspace, $project, 'task.assign'), 403);
            }
            $tasks = Task::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->whereIn('id', array_keys($items))->orderBy('id')->lockForUpdate()->get();
            abort_unless($tasks->count() === count($items), 404);
            foreach ($tasks as $task) {
                Gate::forUser($actor)->authorize($data['action'] === 'archive' ? 'archive' : 'update', $task);
                EditRevision::check($task, ['expected_revision' => $items[$task->id]]);
            }
            foreach ($tasks as $task) {
                if ($data['action'] === 'archive') {
                    app(TaskService::class)->archive($actor, $workspace, $project, $task);
                } else {
                    app(TaskService::class)->save($actor, $workspace, $project, ($data['action'] === 'assign' ? ['assignee_id' => $data['assignee_id'] ?? null] : ['priority' => $data['priority']]) + ['expected_revision' => $items[$task->id]], $task);
                }
            }

            return $tasks->count();
        }, 3);
    }

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
            throw ValidationException::withMessages(['mentions' => 'Chỉ nhắc tên tối đa 20 thành viên đang có quyền xem task.']);
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
                    throw ValidationException::withMessages(['watcher' => 'Tối đa 100 người theo dõi mỗi task.']);
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

    public function storeAttachment(User $actor, Task $task, UploadedFile $file): void
    {
        Gate::forUser($actor)->authorize('attach', $task);
        $originalName = mb_convert_encoding($file->getClientOriginalName(), 'UTF-8', 'UTF-8');
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace('\\', '/', $originalName));
        $name = mb_substr(basename($name), 0, 180);
        $path = $file->storeAs($task->id, Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'task_files');
        try {
            app(TaskWorkflowService::class)->locked($actor, $task, 'attach', function ($task) use ($actor, $file, $name, $path) {
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
                app(TaskHistory::class)->record($actor, $task, 'task.file_uploaded', ['activity_id' => $attachment->id, 'name' => $name, 'size' => $file->getSize()]);
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
        app(TaskWorkflowService::class)->locked($actor, $task, 'attach', function ($task) use ($actor, $id) {
            $attachment = $task->activities()->where('type', 'ATTACHMENT')->findOrFail($id);
            abort_unless($attachment->actor_id === $actor->id || app(TaskPolicy::class)->scope($actor, $task->workspace, $task->project, 'task.assign'), 403);
            $attachment->delete();
            app(TaskHistory::class)->record($actor, $task, 'task.file_removed', ['activity_id' => $id]);
        });
    }

    public static function checkScope(User $actor, Workspace $workspace, ?Project $project): void
    {
        abort_if($project && $project->workspace_id !== $workspace->id, 404);
        abort_unless(app(TaskPolicy::class)->scope($actor, $workspace, $project, 'task.view'), 403);
    }

    public static function indexUrl(Workspace $workspace, ?Project $project, array $query = []): string
    {
        return route($project ? 'workspaces.projects.tasks.index' : 'workspaces.tasks.index', array_merge($project ? [$workspace, $project] : [$workspace], $query));
    }
}
