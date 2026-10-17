<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Support\ProjectAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('project') ? $this->user()->can('update', $this->route('project'))
            : app(ProjectAccess::class)->canCreate($this->user(), $this->route('workspace'));
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::enum(ProjectStatus::class)], 'start_date' => ['nullable', 'date_format:Y-m-d'],
            'deadline' => ['nullable', 'date_format:Y-m-d'], 'parent_project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('workspace_id', $this->route('workspace')->id)]];
    }
}
