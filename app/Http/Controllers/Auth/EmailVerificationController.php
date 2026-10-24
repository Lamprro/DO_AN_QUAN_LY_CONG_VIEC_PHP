<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountMailService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail() ? to_route('workspaces.index') : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return to_route('workspaces.index')->with('success', 'Đã xác minh email. Bạn có thể bắt đầu quản lý công việc.');
    }

    public function send(Request $request, AccountMailService $mail): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return to_route('workspaces.index');
        }
        $sent = $mail->verification($request->user());

        return back()->with($sent ? 'success' : 'warning', $sent ? 'Đã gửi lại thư xác minh. Kiểm tra hộp thư và thư rác.' : 'Chưa gửi được thư xác minh. Hãy thử lại sau hoặc liên hệ quản trị viên.');
    }
}
