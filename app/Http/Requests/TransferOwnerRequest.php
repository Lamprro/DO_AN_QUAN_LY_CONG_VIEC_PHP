<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer', $this->route('workspace'));
    }

    public function rules(): array
    {
        return ['member_id' => ['required', 'uuid', Rule::exists('workspace_members', 'id')->where('workspace_id', $this->route('workspace')->id)->where('status', 'active')]];
    }
}
