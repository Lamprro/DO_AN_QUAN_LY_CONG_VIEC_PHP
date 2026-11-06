<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return ['expected_status' => ['required', Rule::in(['TODO', 'IN_PROGRESS', 'REVIEW', 'DONE', 'CANCELLED'])], 'status' => ['required', Rule::in(['TODO', 'IN_PROGRESS', 'REVIEW', 'DONE', 'CANCELLED'])], 'reason' => ['nullable', 'string', 'max:2000']];
    }
}
