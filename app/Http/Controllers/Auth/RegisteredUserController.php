<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['timezones' => timezone_identifiers_list()]);
    }

    public function store(RegisterRequest $request, RegistrationService $service): RedirectResponse
    {
        $user = $service->register($request->validated());
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('workspaces.index'))->with('success', 'Tài khoản và workspace cá nhân đã được tạo.');
    }
}
