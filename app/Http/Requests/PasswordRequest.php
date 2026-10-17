<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['current_password' => ['bail', 'required', 'string', 'current_password'], 'password' => ['bail', 'required', 'string', 'confirmed', Password::min(8)->letters()->numbers()]];
    }
}
