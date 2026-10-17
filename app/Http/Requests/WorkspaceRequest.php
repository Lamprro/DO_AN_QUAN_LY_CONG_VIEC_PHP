<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('workspace') ? $this->user()->can('update', $this->route('workspace')) : $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'timezone' => ['required', 'string', 'timezone']];
    }
}
