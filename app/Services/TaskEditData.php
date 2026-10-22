<?php

namespace App\Services;

use App\Models\Workspace;
use Carbon\CarbonImmutable;

class TaskEditData
{
    public function presets(Workspace $workspace): array
    {
        return $this->forDate(CarbonImmutable::now($workspace->timezone));
    }

    public function forDate(CarbonImmutable $date): array
    {
        $day = $date->startOfDay();
        $friday = (5 - $day->isoWeekday() + 7) % 7;

        return [
            ['key' => 'today', 'label' => 'Hôm nay 17:30', 'value' => $day->setTime(17, 30)->format('Y-m-d\TH:i')],
            ['key' => 'tomorrow', 'label' => 'Ngày mai 17:30', 'value' => $day->addDay()->setTime(17, 30)->format('Y-m-d\TH:i')],
            ['key' => 'friday', 'label' => $friday === 0 ? 'Hôm nay (Thứ Sáu)' : ($day->isoWeekday() < 5 ? 'Thứ Sáu tuần này' : 'Thứ Sáu tuần sau'), 'value' => $day->addDays($friday)->setTime(17, 30)->format('Y-m-d\TH:i')],
            ['key' => 'week', 'label' => '+7 ngày', 'value' => $day->addDays(7)->setTime(17, 30)->format('Y-m-d\TH:i')],
        ];
    }
}
