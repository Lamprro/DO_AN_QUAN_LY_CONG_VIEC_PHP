<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingRequest;
use App\Services\SettingService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit(Request $request, SettingService $service)
    {
        return view('settings.edit', ['settings' => $service->values($request->user()), 'schedule' => config('work_schedule')]);
    }

    public function update(SettingRequest $request, SettingService $service)
    {
        $service->save($request->user(), $request->validated());

        return back()->with('success', 'Đã lưu cấu hình.');
    }
}
