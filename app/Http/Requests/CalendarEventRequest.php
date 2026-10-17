<?php

namespace App\Http\Requests;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Enums\CalendarVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $format = $this->boolean('all_day') ? 'Y-m-d' : 'Y-m-d\TH:i';

        return ['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:20000'],
            'event_type' => ['required', Rule::enum(CalendarEventType::class)], 'status' => ['required', Rule::enum(CalendarEventStatus::class)],
            'visibility' => ['required', Rule::enum(CalendarVisibility::class)], 'timezone' => ['required', 'timezone:all'],
            'all_day' => ['required', 'boolean'], 'is_busy' => ['required', 'boolean'],
            'starts_at' => ['required', 'date_format:'.$format], 'ends_at' => ['required', 'date_format:'.$format],
            'project_id' => ['nullable', 'uuid'], 'task_id' => ['nullable', 'uuid'],
            'participants' => ['sometimes', 'array', 'max:20'], 'participants.*' => ['required', 'uuid', 'distinct'],
            'version' => [$this->route('event') ? 'required' : 'nullable', 'string', 'size:64'],
            'user_id' => ['prohibited'], 'workspace_id' => ['prohibited'], 'plan_id' => ['prohibited'], 'source' => ['prohibited'],
            'integration_connection_id' => ['prohibited'], 'external_id' => ['prohibited']];
    }
}
