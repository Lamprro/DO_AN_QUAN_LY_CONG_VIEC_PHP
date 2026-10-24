<?php

namespace App\Http\Controllers;

use App\Services\WorkHoursService;
use Illuminate\Http\Request;

class WorkHoursController extends Controller
{
    public function edit(Request $r, WorkHoursService $service)
    {
        return view('settings.work-hours', ['hours' => $service->values($r->user()), 'version' => $service->version($r->user())]);
    }

    public function update(Request $r, WorkHoursService $service)
    {
        $service->save($r->user(), $r->all());

        return back()->with('success', 'Đã cập nhật giờ làm. Kế hoạch cũ giữ snapshot, kế hoạch mới dùng lịch mới.');
    }
}
