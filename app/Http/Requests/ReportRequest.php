<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    /** GET screens default to the current month in the user's timezone. */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('GET')) {
            $month = CarbonImmutable::now($this->user()->timezone)->startOfMonth();
            $defaults = ['from' => $month->format('Y-m-d'), 'to' => $month->addMonth()->format('Y-m-d'), 'project_id' => null, 'subject_user_id' => null, 'include_archived' => false];
            $this->merge(array_replace($defaults, $this->all()));
        }
    }

    public function rules(): array
    {
        return ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d'], 'project_id' => ['nullable', 'uuid'], 'subject_user_id' => ['nullable', 'uuid'],
            'include_archived' => ['sometimes', 'boolean'], 'metrics_json' => ['prohibited'], 'generated_by' => ['prohibited'], 'ai_commentary' => ['prohibited'], 'scope' => ['prohibited'], 'workspace_id' => ['prohibited'], 'timezone' => ['prohibited']];
    }
}
