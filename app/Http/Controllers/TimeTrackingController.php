<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\TimeTracking\TimeTrackingService;
use Illuminate\Http\Request;

class TimeTrackingController extends Controller
{
    public function __construct(private TimeTrackingService $service) {}

    public function index(Request $request)
    {
        return view('timers.index', ['entries' => $this->service->entries($request->user())]);
    }

    public function start(Request $request, Workspace $workspace, Task $task)
    {
        abort_unless($task->workspace_id === $workspace->id, 404);
        $this->service->start($request->user(), $task);

        return to_route('timers.index')->with('success', 'Đã bắt đầu timer.');
    }

    public function stop(Request $request, TimeEntry $entry)
    {
        $this->service->stop($request->user(), $entry);

        return to_route('timers.index')->with('success', 'Đã dừng timer.');
    }

    public function editAdjustment(Request $request, TimeEntry $entry)
    {
        $this->authorize('adjust', $entry);
        if ($request->boolean('fresh')) {
            $request->session()->forget('_old_input');

            return redirect()->to($request->fullUrlWithoutQuery(['fresh']));
        }

        return view('timers.adjust', [
            'entry' => $entry,
            'version' => $this->service->version($entry),
            'audits' => $this->service->adjustments($entry),
        ]);
    }

    public function updateAdjustment(Request $request, TimeEntry $entry)
    {
        // <input type="datetime-local"> may omit seconds.
        foreach (['started_at', 'ended_at'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value)) {
                $request->merge([$field => $value.':00']);
            }
        }
        $validated = $request->validate([
            'version' => 'required|string|size:64',
            'timezone' => 'required|timezone',
            'started_at' => 'required|date_format:Y-m-d\TH:i:s',
            'ended_at' => 'required|date_format:Y-m-d\TH:i:s',
            'note' => 'nullable|string|max:2000',
            'reason' => 'required|string|min:3|max:2000',
        ]);
        $this->service->saveAdjustment($request->user(), $entry, $validated);

        return to_route('timers.adjust', $entry)->with('success', 'Đã điều chỉnh, lưu lịch sử trước/sau. Snapshot cũ giữ nguyên; dashboard mới dùng giờ đã sửa.');
    }

    public function editWorkHours(Request $request)
    {
        return view('settings.work-hours', [
            'hours' => $this->service->workHoursValues($request->user()),
            'version' => $this->service->workHoursVersion($request->user()),
        ]);
    }

    public function updateWorkHours(Request $request)
    {
        $this->service->saveWorkHours($request->user(), $request->all());

        return back()->with('success', 'Đã cập nhật giờ làm. Kế hoạch cũ giữ snapshot, kế hoạch mới dùng lịch mới.');
    }
}
