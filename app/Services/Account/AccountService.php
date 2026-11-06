<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Profile, password change and password reset. Changing e-mail or password ends the user's other sessions. */
class AccountService
{
    public function __construct(private AccountMailService $mail) {}

    /** @return bool whether the e-mail changed (the caller must re-verify it). */
    public function updateProfile(User $user, array $data, string $currentSessionId): bool
    {
        return DB::transaction(function () use ($user, $data, $currentSessionId) {
            $changed = $user->email !== $data['email'];
            $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'timezone'])));
            if ($changed) {
                DB::table('password_reset_tokens')->whereIn('email', [$user->getOriginal('email'), $user->email])->delete();
                $user->email_verified_at = null;
                $user->remember_token = Str::random(60);
            }
            $user->save();
            if ($changed) {
                $this->endOtherSessions($user, $currentSessionId);
            }

            return $changed;
        });
    }

    public function changePassword(User $user, string $password, string $currentSessionId): void
    {
        DB::transaction(function () use ($user, $password, $currentSessionId) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->password = $password;
            $user->remember_token = Str::random(60);
            $user->save();
            $this->endOtherSessions($user, $currentSessionId);
        });
    }

    /** Never reveals whether the account exists. */
    public function sendResetLink(string $email): void
    {
        Cache::lock('account-reset:'.hash('sha256', $email), 30)->block(5, function () use ($email) {
            Password::sendResetLink(['email' => $email, 'status' => 'active'], function (User $user, string $token) {
                if (! $this->mail->reset($user, $token)) {
                    Password::deleteToken($user);
                }
            });
        });
    }

    /** Serialize token verification, password update and token consumption, including concurrent submissions. */
    public function resetPassword(array $data): bool
    {
        $status = Cache::lock('account-reset:'.hash('sha256', $data['email']), 30)->block(5, fn () => DB::transaction(fn () => Password::reset($data + ['status' => 'active'], function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        })));

        return $status === Password::PASSWORD_RESET;
    }

    private function endOtherSessions(User $user, string $currentSessionId): void
    {
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $currentSessionId)->delete();
    }
}
