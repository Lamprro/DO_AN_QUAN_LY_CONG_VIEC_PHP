<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class SavedFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => 'required|string|max:80', 'query' => 'required|array'];
    }
}
