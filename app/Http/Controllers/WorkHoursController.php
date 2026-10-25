<?php

namespace App\Http\Controllers;

use App\Services\TimeTrackingService;
use Illuminate\Http\Request;

class WorkHoursController extends Controller
{
    public function edit(Request $r, TimeTrackingService $service)
    {
        return view('settings.work-hours', ['hours' => $service->workHoursValues($r->user()), 'version' => $service->workHoursVersion($r->user())]);
    }

    public function update(Request $r, TimeTrackingService $service)
    {
        $service->saveWorkHours($r->user(), $r->all());

        return back()->with('success', 'Đã cập nhật giờ làm. Kế hoạch cũ giữ snapshot, kế hoạch mới dùng lịch mới.');
    }
}
