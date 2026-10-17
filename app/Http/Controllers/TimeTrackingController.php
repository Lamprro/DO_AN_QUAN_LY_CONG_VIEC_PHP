<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\TimeTrackingService;
use Illuminate\Http\Request;

class TimeTrackingController extends Controller
{
    public function index(Request $r)
    {
        $entries = TimeEntry::where('user_id', $r->user()->id)->with('task')->latest('started_at')->paginate(25);

        return view('timers.index', compact('entries'));
    }

    public function start(Request $r, Workspace $workspace, Task $task, TimeTrackingService $service)
    {
        abort_unless($task->workspace_id === $workspace->id, 404);
        $service->start($r->user(), $task);

        return to_route('timers.index')->with('success', 'Đã bắt đầu timer.');
    }

    public function stop(Request $r, TimeEntry $entry, TimeTrackingService $service)
    {
        $service->stop($r->user(), $entry);

        return to_route('timers.index')->with('success', 'Đã dừng timer.');
    }
}
