<?php

namespace App\Services\Account;

use App\Models\User;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Account registration, profile and passwords. Email/password changes end other sessions. */
class AccountService
{
    public function __construct(private AccountMailService $mail, private WorkspaceService $workspaces) {}

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
        $lockKey = 'account-reset:'.hash('sha256', $data['email']);
        $status = Cache::lock($lockKey, 30)->block(5, function () use ($data) {
            return DB::transaction(function () use ($data) {
                return Password::reset($data + ['status' => 'active'], function (User $user, string $password) {
                    $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                    event(new PasswordReset($user));
                });
            });
        });

        return $status === Password::PASSWORD_RESET;
    }

    private function endOtherSessions(User $user, string $currentSessionId): void
    {
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $currentSessionId)->delete();
    }

    public function register(array $data): User
    {
        try {
            return DB::transaction(function () use ($data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => mb_strtolower($data['email']),
                    'password' => $data['password'],
                    'timezone' => $data['timezone'],
                    'status' => 'active',
                    'settings' => config('work_management.settings_defaults'),
                ]);
                $this->workspaces->create($user, 'Cá nhân - '.$user->name, $user->timezone, true);

                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (User::where('email', mb_strtolower($data['email']))->exists()) {
                throw ValidationException::withMessages(['email' => 'Email này đã được sử dụng.']);
            }
            throw $e;
        }
    }
}
