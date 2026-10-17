<?php

namespace App\Http\Requests;

use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('GET')) {
            $this->merge(array_replace(app(ReportService::class)->defaults($this->user()), $this->all()));
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d'], 'project_id' => ['nullable', 'uuid'], 'subject_user_id' => ['nullable', 'uuid'],
            'include_archived' => ['sometimes', 'boolean'], 'metrics_json' => ['prohibited'], 'generated_by' => ['prohibited'], 'ai_commentary' => ['prohibited'], 'scope' => ['prohibited'], 'workspace_id' => ['prohibited'], 'timezone' => ['prohibited']];
    }
}
