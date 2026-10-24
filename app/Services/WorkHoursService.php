<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkHoursService
{
    public function values(User $user, ?string $timezone = null): array
    {
        $settings = $user->settings()->value('settings') ?? [];

        return $settings['work_hours'] ?? (array_replace(config('work_schedule'), ['timezone' => $timezone ?? $user->timezone]));
    }

    public function version(User $user): string
    {
        return hash('sha256', json_encode($user->settings()->value('settings')['work_hours'] ?? null));
    }

    public function save(User $user, array $data): void
    {
        $data['windows'] = array_values(array_filter($data['windows'] ?? [], fn ($w) => filled($w['start'] ?? null) || filled($w['end'] ?? null)));
        $data = Validator::make($data, ['version' => 'required|string|size:64', 'timezone' => 'required|timezone', 'weekdays' => 'required|array|min:1|max:7', 'weekdays.*' => 'required|integer|between:1,7|distinct', 'windows' => 'required|array|min:1|max:4', 'windows.*.start' => 'required|date_format:H:i', 'windows.*.end' => 'required|date_format:H:i', 'slot_minutes' => ['required', Rule::in([5, 10, 15, 20, 30, 60])]])->validate();
        usort($data['windows'], fn ($a, $b) => strcmp($a['start'], $b['start']));
        $end = '00:00';
        foreach ($data['windows'] as $w) {
            if ($w['start'] < $end || $w['end'] <= $w['start']) {
                throw ValidationException::withMessages(['windows' => 'Các ca làm phải kết thúc sau bắt đầu, cùng ngày và không chồng lấn.']);
            }
            $end = $w['end'];
        }
        DB::transaction(function () use ($user, $data) {
            User::lockForUpdate()->findOrFail($user->id);
            if (! hash_equals($this->version($user), $data['version'])) {
                throw ValidationException::withMessages(['version' => 'Giờ làm đã thay đổi. Tải lại trước khi lưu.']);
            }
            $settings = $user->settings()->value('settings') ?? config('work_management.settings_defaults');
            $settings['work_hours'] = ['timezone' => $data['timezone'], 'weekdays' => array_map('intval', $data['weekdays']), 'windows' => $data['windows'], 'slot_minutes' => (int) $data['slot_minutes']];
            UserSetting::updateOrCreate(['user_id' => $user->id], ['settings' => $settings]);
        }, 3);
    }
}
