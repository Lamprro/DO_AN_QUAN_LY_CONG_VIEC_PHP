<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SavedTaskFilter;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Support\EditRevision;
use App\Support\TaskFilters;
use App\Support\TaskUtilityScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskUtilityService
{
    public function saveFilter(User $actor, Workspace $workspace, ?Project $project, string $name, array $query): SavedTaskFilter
    {
        return DB::transaction(function () use ($actor, $workspace, $project, $name, $query) {
            User::lockForUpdate()->findOrFail($actor->id);
            TaskUtilityScope::check($actor, $workspace, $project);
            $q = SavedTaskFilter::where('user_id', $actor->id)->where('workspace_id', $workspace->id)->where('project_id', $project?->id);
            if ((clone $q)->count() >= 20 || (clone $q)->where('name', $name)->exists()) {
                throw ValidationException::withMessages(['name' => 'Tối đa20 bộ lọc trong phạm vi; tên không được trùng.']);
            }

            return SavedTaskFilter::create(['user_id' => $actor->id, 'workspace_id' => $workspace->id, 'project_id' => $project?->id, 'name' => $name, 'query' => TaskFilters::clean($query)]);
        }, 3);
    }

    public function bulk(User $actor, Workspace $workspace, ?Project $project, array $data): int
    {
        return DB::transaction(function () use ($actor, $workspace, $project, $data) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            TaskUtilityScope::check($actor, $workspace, $project);
            $items = $data['items'];
            if ($data['action'] === 'assign') {
                abort_unless(app(TaskPolicy::class)->scope($actor, $workspace, $project, 'task.assign'), 403);
            }$tasks = Task::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->whereIn('id', array_keys($items))->orderBy('id')->lockForUpdate()->get();
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
}
