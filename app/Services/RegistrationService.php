<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function __construct(private WorkspaceService $workspaces) {}

    public function register(array $data): User
    {
        try {
            return DB::transaction(function () use ($data) {
                $user = User::create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'], 'timezone' => $data['timezone'], 'status' => 'active']);
                UserSetting::create(['user_id' => $user->id, 'settings' => config('work_management.settings_defaults')]);
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
