<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountMailService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, AccountMailService $mail): RedirectResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? Str::lower(trim($request->input('email'))) : $request->input('email')]);
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        Cache::lock('account-reset:'.hash('sha256', $data['email']), 30)->block(5, function () use ($data, $mail) {
            Password::sendResetLink($data + ['status' => 'active'], function (User $user, string $token) use ($mail) {
                if (! $mail->reset($user, $token)) {
                    Password::deleteToken($user);
                }
            });
        });

        // Same response for unknown, inactive, throttled and eligible accounts.
        return back()->with('success', 'Nếu email thuộc tài khoản đang hoạt động, hệ thống sẽ gửi liên kết đặt lại mật khẩu. Kiểm tra hộp thư và thư rác; nếu chưa nhận được, hãy thử lại sau ít phút hoặc liên hệ quản trị viên.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? Str::lower(trim($request->input('email'))) : $request->input('email')]);
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers(), 'max:200'],
        ]);
        // Serialize token verification, password update and token consumption, including concurrent submissions.
        $status = Cache::lock('account-reset:'.hash('sha256', $data['email']), 30)->block(5, fn () => DB::transaction(fn () => Password::reset($data + ['status' => 'active'], function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        })
        ));
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Liên kết không hợp lệ, đã dùng hoặc hết hạn. Vui lòng yêu cầu liên kết mới.'])->onlyInput('email');
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('success', 'Đã đổi mật khẩu và kết thúc các phiên cũ. Vui lòng đăng nhập lại.');
    }
}
