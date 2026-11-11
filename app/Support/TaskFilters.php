<?php

namespace App\Support;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Validation rules for the task list query string (shared by list, saved filters). */
class TaskFilters
{
    public static function rules(): array
    {
        return [
            'view' => 'nullable|in:list,board',
            'q' => 'nullable|string|max:200',
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => 'nullable|uuid',
            'archived' => ['nullable', Rule::in(['active', 'archived', 'all'])],
            'sort' => ['nullable', Rule::in(['code', 'title', 'priority', 'deadline_at', 'created_at'])],
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|in:10,25,50',
            'preset' => 'nullable|in:all,mine,review,blocked,overdue',
        ];
    }

    public static function clean(array $input): array
    {
        return Validator::make(array_intersect_key($input, self::rules()), self::rules())->validate();
    }
}
