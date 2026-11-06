<?php

namespace App\Http\Requests\Task;

use App\Rules\AttachmentFormat;
use Illuminate\Foundation\Http\FormRequest;

class TaskAttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        return ['file' => ['bail', 'required', 'file', 'max:'.config('tasks.attachment_max_kb'), new AttachmentFormat]];
    }
}
