<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function rules(): array
    {
        return ['role_id' => ['required', 'uuid', Rule::exists('roles', 'id')->where('workspace_id', $this->route('workspace')->id)], 'status' => ['required', Rule::in(['active', 'suspended', 'left'])]];
    }
}
