<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class InvitationRequest extends EmailFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('workspace'));
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255'], 'role_id' => ['required', 'uuid', Rule::exists('roles', 'id')->where('workspace_id', $this->route('workspace')->id)]];
    }
}
