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

    public function index(Request $r)
    {
        return view('timers.index', ['entries' => $this->service->entries($r->user())]);
    }

    public function start(Request $r, Workspace $workspace, Task $task)
    {
        abort_unless($task->workspace_id === $workspace->id, 404);
        $this->service->start($r->user(), $task);

        return to_route('timers.index')->with('success', 'Đã bắt đầu timer.');
    }

    public function stop(Request $r, TimeEntry $entry)
    {
        $this->service->stop($r->user(), $entry);

        return to_route('timers.index')->with('success', 'Đã dừng timer.');
    }
}
