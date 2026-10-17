<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task ? $this->user()->can('update', $task)
            : app(TaskPolicy::class)->projectWritable($this->route('project')) && app(TaskPolicy::class)->scope($this->user(), $this->route('workspace'), $this->route('project'), 'task.create');
    }

    public function rules(): array
    {
        return ['expected_revision' => [$this->route('task') ? 'required' : 'prohibited', 'integer', 'min:1'], 'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('tasks', 'code')->where('workspace_id', $this->route('workspace')->id)->ignore($this->route('task')?->id)],
            'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:20000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)], 'estimate_minutes' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'deadline_at' => ['nullable', 'date_format:Y-m-d\TH:i'], 'assignee_id' => ['nullable', 'uuid'], 'reviewer_id' => ['nullable', 'uuid'],
            'status' => ['prohibited'], 'task_type' => ['sometimes', Rule::enum(TaskType::class)], 'parent_task_id' => ['nullable', 'uuid'], 'group_label' => ['nullable', 'string', 'max:120'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'started_at' => ['prohibited'], 'completed_at' => ['prohibited'], 'archived_at' => ['prohibited'], 'project_id' => ['prohibited'], 'workspace_id' => ['prohibited']];
    }
}
