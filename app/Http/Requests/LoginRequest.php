<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends EmailFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email'], 'password' => ['required', 'string'], 'remember' => ['sometimes', 'boolean']];
    }

    public function authenticate(): void
    {
        $key = 'login:'.hash('sha256', $this->string('email').'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Đăng nhập sai nhiều lần. Thử lại sau '.RateLimiter::availableIn($key).' giây.']);
        }
        if (! Auth::attempt(['email' => $this->input('email'), 'password' => $this->input('password'), 'status' => 'active'], $this->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email, mật khẩu không đúng hoặc tài khoản không hoạt động.']);
        }
        RateLimiter::clear($key);
    }
}
