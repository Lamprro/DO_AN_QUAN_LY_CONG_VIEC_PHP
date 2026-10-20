<?php

namespace App\Support;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TaskFilters
{
    public static function rules(): array
    {
        return ['view' => 'nullable|in:list,board', 'q' => 'nullable|string|max:200', 'status' => ['nullable', Rule::enum(TaskStatus::class)], 'priority' => ['nullable', Rule::enum(TaskPriority::class)], 'assignee_id' => 'nullable|uuid', 'archived' => ['nullable', Rule::in(['active', 'archived', 'all'])], 'sort' => ['nullable', Rule::in(['code', 'title', 'priority', 'deadline_at', 'created_at'])], 'direction' => 'nullable|in:asc,desc', 'per_page' => 'nullable|in:10,25,50', 'preset' => 'nullable|in:all,mine,review,blocked,overdue'];
    }

    public static function clean(array $input): array
    {
        return Validator::make(array_intersect_key($input, self::rules()), self::rules())->validate();
    }

    public static function applyPreset($query, array $filters, $actor, $workspace, $project): void
    {
        match ($filters['preset'] ?? 'all') {
            'mine' => $query->where('assignee_id', $actor->id)->where('task_type', '!=', 'GROUP'),
            'review' => $query->whereNull('archived_at')->when(! app(TaskPolicy::class)->projectWritable($project), fn ($q) => $q->whereRaw('1=0'))->where('status', 'REVIEW')->where('task_type', '!=', 'GROUP')->where(function ($q) use ($actor, $workspace) {
                if ($workspace->owner_id !== $actor->id) {
                    $q->where('reviewer_id', $actor->id);
                }
            })->when(! app(TaskPolicy::class)->scope($actor, $workspace, $project, 'task.review'), fn ($q) => $q->whereRaw('1=0')),
            'blocked' => $query->where('task_type', '!=', 'GROUP')->whereNotIn('status', ['DONE', 'CANCELLED'])->whereHas('blockers', fn ($q) => $q->where('status', 'open')),
            'overdue' => $query->where('task_type', 'WORK')->whereNotIn('status', ['DONE', 'CANCELLED'])->where('deadline_at', '<', now()),
            default => null
        };
    }
}
