<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Task template settings. On store the selected task ids are required;
 * on update the current revision is required instead.
 */
class TaskTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        $updating = $this->route('template') !== null;

        return [
            'name' => ['required', 'string', 'max:160'],
            'frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'interval' => ['required', 'integer', 'between:1,12'],
            'timezone' => ['required', 'timezone'],
            'starts_on' => ['nullable', 'required_with:frequency', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'local_time' => ['nullable', 'required_with:frequency', 'date_format:H:i'],
            'deadline_offset_days' => ['nullable', 'integer', 'between:0,365'],
            'task_ids' => [$updating ? 'prohibited' : 'required', 'array', 'min:1', 'max:50'],
            'task_ids.*' => ['required', 'uuid', 'distinct'],
            'revision' => [$updating ? 'required' : 'prohibited', 'integer', 'min:1'],
            'definition' => ['prohibited'], 'status' => ['prohibited'], 'next_run_at' => ['prohibited'], 'created_by' => ['prohibited'],
        ];
    }
}
