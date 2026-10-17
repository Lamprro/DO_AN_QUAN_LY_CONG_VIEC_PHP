<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProfileRequest extends EmailFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $changingEmail = $this->input('email') !== $this->user()->email;

        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)], 'timezone' => ['required', 'string', 'timezone'], 'current_password' => ['bail', $changingEmail ? 'required' : 'nullable', 'string', 'current_password']];
    }
}
