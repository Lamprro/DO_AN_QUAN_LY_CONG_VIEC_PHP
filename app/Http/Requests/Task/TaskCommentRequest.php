<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class TaskCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return ['content' => ['required', 'string', 'max:10000'], 'mentions' => ['sometimes', 'array', 'max:20'], 'mentions.*' => ['required', 'uuid', 'distinct']];
    }
}
