<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\DB;

class SettingService
{
    public function values(User $user): array
    {
        $stored = $user->settings()->value('settings') ?? [];
        if (is_string($stored)) {
            $stored = json_decode($stored, true) ?? [];
        }
        $defaults = config('work_management.settings_defaults');

        return ['language' => in_array($stored['language'] ?? null, ['vi', 'en'], true) ? $stored['language'] : $defaults['language'],
            'theme' => in_array($stored['theme'] ?? null, ['system', 'light', 'dark'], true) ? $stored['theme'] : $defaults['theme'],
            'notifications' => ['in_app' => (bool) ($stored['notifications']['in_app'] ?? $defaults['notifications']['in_app']), 'email' => false, 'telegram' => false]];
    }

    public function save(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            User::lockForUpdate()->findOrFail($user->id);
            UserSetting::updateOrCreate(['user_id' => $user->id], ['settings' => ['language' => $data['language'], 'theme' => $data['theme'],
                'notifications' => ['in_app' => (bool) $data['notifications']['in_app'], 'email' => false, 'telegram' => false]]]);
        }, 3);
    }
}
