<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('project'));
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return ['workspace_member_id' => [$this->route('projectMember') ? 'prohibited' : 'required', 'uuid', Rule::exists('workspace_members', 'id')->where('workspace_id', $workspace->id)],
            'role_id' => ['required', 'uuid', Rule::exists('roles', 'id')->where('workspace_id', $workspace->id)],
            'status' => ['required', Rule::in(['active', 'suspended', 'left'])]];
    }
}
