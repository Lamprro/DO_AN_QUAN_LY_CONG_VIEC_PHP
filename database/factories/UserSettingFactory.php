<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSetting>
 */
class UserSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'settings' => config('work_management.settings_defaults', [
                'theme' => 'light',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ]),
        ];
    }
}
