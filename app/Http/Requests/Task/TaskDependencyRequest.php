<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class TaskDependencyRequest extends FormRequest
{
    public function rules(): array
    {
        return ['predecessor_task_id' => ['required', 'uuid'], 'lag_minutes' => ['required', 'integer', 'min:0', 'max:525600']];
    }
}
