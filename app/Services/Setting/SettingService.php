<?php

namespace App\Services\Setting;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Single entry point for users.settings (language, theme, notifications, work_hours). */
class SettingService
{
    /** Raw stored JSON, always re-read from the database so callers inside a lock see committed values. */
    public function stored(User $user): array
    {
        $stored = User::whereKey($user->id)->value('settings');
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return is_array($stored) ? $stored : [];
    }

    /** Replace the stored JSON. Caller must already hold the user row lock. */
    public function store(User $user, array $settings): void
    {
        $fresh = User::findOrFail($user->id);
        $fresh->settings = $settings;
        $fresh->save();
        $user->setAttribute('settings', $settings)->syncOriginalAttribute('settings');
    }

    public function values(User $user): array
    {
        $stored = $this->stored($user);
        $defaults = config('work_management.settings_defaults');

        return [
            'language' => in_array($stored['language'] ?? null, ['vi', 'en'], true) ? $stored['language'] : $defaults['language'],
            'theme' => in_array($stored['theme'] ?? null, ['system', 'light', 'dark'], true) ? $stored['theme'] : $defaults['theme'],
            'notifications' => [
                'in_app' => (bool) ($stored['notifications']['in_app'] ?? $defaults['notifications']['in_app']),
                'email' => false,
                'telegram' => false,
            ],
        ];
    }

    public function save(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            User::lockForUpdate()->findOrFail($user->id);
            $this->store($user, array_replace($this->stored($user), [
                'language' => $data['language'],
                'theme' => $data['theme'],
                'notifications' => [
                    'in_app' => (bool) $data['notifications']['in_app'],
                    'email' => false,
                    'telegram' => false,
                ],
            ]));
        }, 3);
    }
}
