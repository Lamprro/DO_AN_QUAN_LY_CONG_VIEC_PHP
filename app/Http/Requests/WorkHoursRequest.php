<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Personal working hours used by the local scheduler (stored in users.settings). */
class WorkHoursRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Empty rows of the windows table are ignored, not rejected.
        $windows = array_filter((array) $this->input('windows', []), fn ($window) => is_array($window) && (filled($window['start'] ?? null) || filled($window['end'] ?? null)));
        $this->merge(['windows' => array_values($windows)]);
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'size:64'],
            'timezone' => ['required', 'timezone'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'windows' => ['required', 'array', 'min:1', 'max:4'],
            'windows.*.start' => ['required', 'date_format:H:i'],
            'windows.*.end' => ['required', 'date_format:H:i'],
            'slot_minutes' => ['required', Rule::in([5, 10, 15, 20, 30, 60])],
        ];
    }
}
