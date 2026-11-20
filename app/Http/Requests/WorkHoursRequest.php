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
        $windows = array_filter(
            (array) $this->input('windows', []),
            fn ($window) => is_array($window) && (filled($window['start'] ?? null) || filled($window['end'] ?? null))
        );

        $normalized = [];
        foreach ($windows as $window) {
            $start = $window['start'] ?? null;
            $end = $window['end'] ?? null;
            if (is_string($start) && preg_match('/^(\d{1,2}):(\d{2})$/', trim($start), $m)) {
                $start = sprintf('%02d:%s', (int) $m[1], $m[2]);
            }
            if (is_string($end) && preg_match('/^(\d{1,2}):(\d{2})$/', trim($end), $m)) {
                $end = sprintf('%02d:%s', (int) $m[1], $m[2]);
            }
            $normalized[] = ['start' => $start, 'end' => $end];
        }

        $this->merge(['windows' => array_values($normalized)]);
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
