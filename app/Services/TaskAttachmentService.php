<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TaskAttachmentService
{
    public function __construct(private TaskWorkflowService $workflow, private TaskHistory $history, private TaskPolicy $policy) {}

    public function store(User $actor, Task $task, UploadedFile $file): void
    {
        Gate::forUser($actor)->authorize('attach', $task);
        $originalName = mb_convert_encoding($file->getClientOriginalName(), 'UTF-8', 'UTF-8');
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace('\\', '/', $originalName));
        $name = mb_substr(basename($name), 0, 180);
        $path = $file->storeAs($task->id, Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'task_files');
        try {
            $this->workflow->locked($actor, $task, 'attach', function ($task) use ($actor, $file, $name, $path) {
                $attachment = TaskActivity::create(['task_id' => $task->id, 'actor_id' => $actor->id, 'type' => 'ATTACHMENT', 'source' => 'user', 'content' => $name,
                    'data' => ['disk' => 'task_files', 'path' => $path, 'original_name' => $name, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())]]);
                $this->history->record($actor, $task, 'task.file_uploaded', ['activity_id' => $attachment->id, 'name' => $name, 'size' => $file->getSize()]);
            });
        } catch (Throwable $error) {
            Storage::disk('task_files')->delete($path);
            throw $error;
        }
    }

    public function download(User $actor, Task $task, string $id)
    {
        Gate::forUser($actor)->authorize('view', $task);
        $attachment = $task->activities()->where('type', 'ATTACHMENT')->findOrFail($id);
        $data = $attachment->data;
        abort_unless(($data['disk'] ?? null) === 'task_files' && str_starts_with($data['path'] ?? '', $task->id.'/') && ! str_contains($data['path'], '..'), 404);
        abort_unless(Storage::disk('task_files')->exists($data['path']), 404);

        return Storage::disk('task_files')->download($data['path'], $data['original_name'], ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function remove(User $actor, Task $task, string $id): void
    {
        $this->workflow->locked($actor, $task, 'attach', function ($task) use ($actor, $id) {
            $attachment = $task->activities()->where('type', 'ATTACHMENT')->findOrFail($id);
            abort_unless($attachment->actor_id === $actor->id || $this->policy->scope($actor, $task->workspace, $task->project, 'task.assign'), 403);
            $attachment->delete(); // Private bytes retained for audit; deleted rows cannot be downloaded.
            $this->history->record($actor, $task, 'task.file_removed', ['activity_id' => $id]);
        });
    }
}
