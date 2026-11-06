<?php

namespace App\Services\Task;

use App\Authorization\ProjectAccess;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Who may be mentioned on / watch a task: active members that can view it. */
class TaskParticipants
{
    public function __construct(private ProjectAccess $projects) {}

    public function people(Task $task): Collection
    {
        return $this->projects->eligibleMembers($task->workspace, $task->project)
            ->filter(fn ($member) => Gate::forUser($member->user)->allows('view', $task));
    }

    public function mentions(Task $task, array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if (count($ids) > 20 || array_diff($ids, $this->people($task)->pluck('user_id')->all())) {
            throw ValidationException::withMessages(['mentions' => 'Chỉ nhắc tên tối đa 20 thành viên đang có quyền xem task.']);
        }

        return $ids;
    }
}
