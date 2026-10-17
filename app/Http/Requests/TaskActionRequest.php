<?php

namespace App\Http\Requests;

use App\Rules\AttachmentFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        $name = $this->route()->getName();
        $ability = str_contains($name, '.dependencies.') ? 'link' : (str_contains($name, '.blockers.') ? 'block' : (str_contains($name, '.comments.') ? 'comment' : (str_contains($name, '.attachments.') ? 'attach' : null)));

        return $ability ? $this->user()->can($ability, $task) : $this->user()->can('view', $task);
    }

    public function rules(): array
    {
        $name = $this->route()->getName();
        if (str_ends_with($name, '.status.update')) {
            return ['expected_status' => ['required', Rule::in(['TODO', 'IN_PROGRESS', 'REVIEW', 'DONE', 'CANCELLED'])], 'status' => ['required', Rule::in(['TODO', 'IN_PROGRESS', 'REVIEW', 'DONE', 'CANCELLED'])], 'reason' => ['nullable', 'string', 'max:2000']];
        }
        if (str_ends_with($name, '.dependencies.store')) {
            return ['predecessor_task_id' => ['required', 'uuid'], 'lag_minutes' => ['required', 'integer', 'min:0', 'max:525600']];
        }
        if (str_ends_with($name, '.blockers.store')) {
            return ['reason' => ['required', 'string', 'max:4000']];
        }
        if (str_ends_with($name, '.blockers.update')) {
            return ['status' => ['required', Rule::in(['open', 'resolved'])], 'reason' => ['nullable', 'string', 'max:4000']];
        }
        if (str_contains($name, '.comments.') && ! $this->isMethod('DELETE')) {
            return ['content' => ['required', 'string', 'max:10000']];
        }
        if (str_ends_with($name, '.attachments.store')) {
            return ['file' => ['bail', 'required', 'file', 'max:'.config('tasks.attachment_max_kb'), new AttachmentFormat]];
        }

        return [];
    }
}
