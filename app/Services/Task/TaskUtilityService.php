<?php

namespace App\Services\Task;

use App\Authorization\TaskAccess;
use App\Models\Project;
use App\Models\SavedTaskFilter;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskWatcher;
use App\Models\User;
use App\Models\Workspace;
use App\Support\EditRevision;
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
    public function __construct(
        private TaskAccess $tasks,
        private TaskService $taskService,
        private TaskWorkflowService $workflow,
        private TaskHistory $history,
        private TaskParticipants $participants,
    ) {}

    public function saveFilter(User $actor, Workspace $workspace, ?Project $project, string $name, array $query): SavedTaskFilter
    {
        return DB::transaction(function () use ($actor, $workspace, $project, $name, $query) {
            User::lockForUpdate()->findOrFail($actor->id);
            $this->assertScope($actor, $workspace, $project);
            $q = SavedTaskFilter::where('user_id', $actor->id)->where('workspace_id', $workspace->id)->where('project_id', $project?->id);
            if ((clone $q)->count() >= 20 || (clone $q)->where('name', $name)->exists()) {
                throw ValidationException::withMessages(['name' => 'Tối đa 20 bộ lọc trong phạm vi; tên không được trùng.']);
            }

            return SavedTaskFilter::create(['user_id' => $actor->id, 'workspace_id' => $workspace->id, 'project_id' => $project?->id, 'name' => $name, 'query' => TaskFilters::clean($query)]);
        }, 3);
    }

    /** A saved filter owned by the actor in exactly this scope. */
    public function findFilter(User $actor, Workspace $workspace, ?Project $project, SavedTaskFilter $saved): SavedTaskFilter
    {
        $this->assertScope($actor, $workspace, $project);
        abort_unless($saved->user_id === $actor->id && $saved->workspace_id === $workspace->id && $saved->project_id === $project?->id, 404);

        return $saved;
    }

    public function deleteFilter(User $actor, Workspace $workspace, ?Project $project, SavedTaskFilter $saved): void
    {
        $this->findFilter($actor, $workspace, $project, $saved)->delete();
    }

    public function bulk(User $actor, Workspace $workspace, ?Project $project, array $data): int
    {
        return DB::transaction(function () use ($actor, $workspace, $project, $data) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $this->assertScope($actor, $workspace, $project);
            $items = $data['items'];
            if ($data['action'] === 'assign') {
                abort_unless($this->tasks->scope($actor, $workspace, $project, 'task.assign'), 403);
            }
            $tasks = Task::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->whereIn('id', array_keys($items))->orderBy('id')->lockForUpdate()->get();
            abort_unless($tasks->count() === count($items), 404);
            foreach ($tasks as $task) {
                Gate::forUser($actor)->authorize($data['action'] === 'archive' ? 'archive' : 'update', $task);
                EditRevision::check($task, ['expected_revision' => $items[$task->id]]);
            }
            foreach ($tasks as $task) {
                if ($data['action'] === 'archive') {
                    $this->taskService->archive($actor, $workspace, $project, $task);
                } else {
                    $this->taskService->save($actor, $workspace, $project, ($data['action'] === 'assign' ? ['assignee_id' => $data['assignee_id'] ?? null] : ['priority' => $data['priority']]) + ['expected_revision' => $items[$task->id]], $task);
                }
            }

            return $tasks->count();
        }, 3);
    }

    public function viewData(Task $task): array
    {
        $people = $this->participants->people($task);
        $ready = Schema::hasTable('task_watchers');
        $watchers = $ready ? TaskWatcher::where('task_id', $task->id)->with('user')->get()->filter(fn ($w) => $people->contains('user_id', $w->user_id)) : collect();

        return ['collaborationReady' => $ready, 'collaborationPeople' => $people, 'watchers' => $watchers];
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
            $this->workflow->locked($actor, $task, 'attach', function ($task) use ($actor, $file, $name, $path) {
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
                $this->history->record($actor, $task, 'task.file_uploaded', ['activity_id' => $attachment->id, 'name' => $name, 'size' => $file->getSize()]);
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
        $this->workflow->locked($actor, $task, 'attach', function ($task) use ($actor, $id) {
            $attachment = $task->activities()->where('type', 'ATTACHMENT')->findOrFail($id);
            abort_unless($attachment->actor_id === $actor->id || $this->tasks->scope($actor, $task->workspace, $task->project, 'task.assign'), 403);
            $attachment->delete();
            $this->history->record($actor, $task, 'task.file_removed', ['activity_id' => $id]);
        });
    }

    /** The actor can see tasks in this scope (404 if the project is outside the workspace). */
    public function assertScope(User $actor, Workspace $workspace, ?Project $project): void
    {
        abort_if($project && $project->workspace_id !== $workspace->id, 404);
        abort_unless($this->tasks->scope($actor, $workspace, $project, 'task.view'), 403);
    }
}
