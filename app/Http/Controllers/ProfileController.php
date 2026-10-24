<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user(), 'timezones' => timezone_identifiers_list()]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = $request->user();
            $changed = $user->email !== $request->validated('email');
            $user->fill($request->safe()->only(['name', 'email', 'timezone']));
            if ($changed) {
                DB::table('password_reset_tokens')->whereIn('email', [$user->getOriginal('email'), $user->email])->delete();
                $user->email_verified_at = null;
                $user->remember_token = Str::random(60);
            }
            $user->save();
            if ($changed) {
                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            }
        });
        $request->session()->regenerate();

        if (config('security.require_verified_email') && ! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();

            return to_route('verification.notice');
        }

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function password(PasswordRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = $request->user();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->password = $request->validated('password');
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        });
        $request->session()->regenerate();

        return back()->with('success', 'Đã đổi mật khẩu và kết thúc các phiên đăng nhập khác.');
    }
}
