<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** items = [task_id => expected_revision]. */
class BulkTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => 'required|in:priority,assign,archive',
            'items' => 'required|array|min:1|max:50',
            'items.*' => 'required|integer|min:1',
            'priority' => ['required_if:action,priority', 'nullable', Rule::enum(TaskPriority::class)],
            'assignee_id' => 'nullable|uuid',
        ];
    }

    protected function passedValidation(): void
    {
        foreach (array_keys($this->input('items')) as $id) {
            abort_unless(Str::isUuid((string) $id), 422);
        }
    }
}
