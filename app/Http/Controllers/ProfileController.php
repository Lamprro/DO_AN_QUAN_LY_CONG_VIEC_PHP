<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Services\Account\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user(), 'timezones' => timezone_identifiers_list()]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $this->accounts->updateProfile($request->user(), $request->validated(), $request->session()->getId());
        $request->session()->regenerate();
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();

            return to_route('verification.notice');
        }

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function password(PasswordRequest $request): RedirectResponse
    {
        $this->accounts->changePassword($request->user(), $request->validated('password'), $request->session()->getId());
        $request->session()->regenerate();

        return back()->with('success', 'Đã đổi mật khẩu và kết thúc các phiên đăng nhập khác.');
    }
}
