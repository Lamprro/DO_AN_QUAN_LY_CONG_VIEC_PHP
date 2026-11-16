<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Team calendar: propose a meeting or task block to another member. */
class CalendarProposalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'uuid'],
            'recipient_id' => ['required', 'uuid'],
            'task_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:4000'],
            'event_type' => ['required', Rule::in(['MEETING', 'TASK_BLOCK'])],
            'visibility' => ['required', Rule::in(['private', 'project', 'workspace'])],
            'timezone' => ['required', 'timezone'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'user_id' => ['prohibited'], 'proposed_by' => ['prohibited'], 'status' => ['prohibited'], 'workspace_id' => ['prohibited'],
        ];
    }
}
