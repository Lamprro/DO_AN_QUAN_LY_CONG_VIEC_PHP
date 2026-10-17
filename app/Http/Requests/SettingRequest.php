<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['language' => ['required', Rule::in(['vi', 'en'])], 'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'notifications' => ['required', 'array:in_app,email,telegram'], 'notifications.in_app' => ['required', 'boolean'],
            'notifications.email' => ['sometimes', Rule::in(['0', 0, false])], 'notifications.telegram' => ['sometimes', Rule::in(['0', 0, false])],
            'settings' => ['prohibited'], 'permissions' => ['prohibited'], 'token' => ['prohibited'], 'working_hours' => ['prohibited'], 'user_id' => ['prohibited']];
    }
}
