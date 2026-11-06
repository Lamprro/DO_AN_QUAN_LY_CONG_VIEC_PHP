<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST reports a blocker; PATCH changes its status. */
class TaskBlockerRequest extends FormRequest
{
    public function rules(): array
    {
        return $this->isMethod('PATCH')
            ? ['status' => ['required', Rule::in(['open', 'resolved'])], 'reason' => ['nullable', 'string', 'max:4000']]
            : ['reason' => ['required', 'string', 'max:4000']];
    }
}
