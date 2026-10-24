<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AccountMailService
{
    public function reset(User $user, string $token): bool
    {
        $url = rtrim(config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $user->email], false);

        return $this->send($user, $url, 'Đặt lại mật khẩu', (int) config('auth.passwords.users.expire'), 'reset');
    }

    public function verification(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }
        // Relative signature + configured origin prevents links using a supplied Host header.
        $path = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())], absolute: false);

        return $this->send($user, rtrim(config('app.url'), '/').$path, 'Xác minh địa chỉ email', 60, 'verification');
    }

    private function send(User $user, string $url, string $heading, int $minutes, string $kind): bool
    {
        try {
            Mail::send('mail.account-link', compact('url', 'heading', 'minutes', 'kind'), function ($message) use ($user, $heading) {
                $message->to($user->email)->subject($heading.' · Work Management');
            });

            return true;
        } catch (\Throwable $error) {
            // Transport exceptions can contain SMTP credentials, addresses or reset links.
            Log::error('account_mail.delivery_failed', ['kind' => $kind, 'error_type' => $error::class]);

            return false;
        }
    }
}
