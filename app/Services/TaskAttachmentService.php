<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class TaskAttachmentService
{
    public function store(User $actor, Task $task, UploadedFile $file): void
    {
        app(TaskUtilityService::class)->storeAttachment($actor, $task, $file);
    }

    public function download(User $actor, Task $task, string $id)
    {
        return app(TaskUtilityService::class)->downloadAttachment($actor, $task, $id);
    }

    public function remove(User $actor, Task $task, string $id): void
    {
        app(TaskUtilityService::class)->removeAttachment($actor, $task, $id);
    }
}
