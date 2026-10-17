<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class RegisterRequest extends EmailFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()], 'timezone' => ['required', 'string', 'timezone']];
    }
}
