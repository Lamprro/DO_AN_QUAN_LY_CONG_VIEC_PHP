<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Services\TimeTracking\TimeTrackingService;
use Illuminate\Http\Request;

class TimeAdjustmentController extends Controller
{
    public function __construct(private TimeTrackingService $service) {}

    public function edit(Request $r, TimeEntry $entry)
    {
        $this->authorize('adjust', $entry);
        if ($r->boolean('fresh')) {
            $r->session()->forget('_old_input');

            return redirect()->to($r->fullUrlWithoutQuery(['fresh']));
        }

        return view('timers.adjust', ['entry' => $entry, 'version' => $this->service->version($entry), 'audits' => $this->service->adjustments($entry)]);
    }

    public function update(Request $r, TimeEntry $entry)
    {
        // <input type="datetime-local"> may omit seconds.
        foreach (['started_at', 'ended_at'] as $field) {
            $value = $r->input($field);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value)) {
                $r->merge([$field => $value.':00']);
            }
        }
        $v = $r->validate(['version' => 'required|string|size:64', 'timezone' => 'required|timezone', 'started_at' => 'required|date_format:Y-m-d\TH:i:s', 'ended_at' => 'required|date_format:Y-m-d\TH:i:s', 'note' => 'nullable|string|max:2000', 'reason' => 'required|string|min:3|max:2000']);
        $this->service->saveAdjustment($r->user(), $entry, $v);

        return to_route('timers.adjust', $entry)->with('success', 'Đã điều chỉnh, lưu lịch sử trước/sau. Snapshot cũ giữ nguyên; dashboard mới dùng giờ đã sửa.');
    }
}
