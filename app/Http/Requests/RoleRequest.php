<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('permissions')) {
            $this->merge(['permissions' => []]);
        }
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->where('workspace_id', $this->route('workspace')->id)->ignore($this->route('role')?->id)], 'permissions' => ['array', 'max:100'], 'permissions.*' => ['string', 'distinct', Rule::in(config('permissions.catalog'))]];
    }
}
