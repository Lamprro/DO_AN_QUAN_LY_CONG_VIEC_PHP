<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Queue\QueueObservation;
use App\Services\Task\TaskReminderService;
use Illuminate\Http\Request;

class UtilityObservationController extends Controller
{
    public function reminders(Request $request, Workspace $workspace, TaskReminderService $service)
    {
        return view('utilities.reminders', ['workspace' => $workspace, 'reminders' => $service->visibleFor($request->user(), $workspace)]);
    }

    public function scan(Request $request, Workspace $workspace, TaskReminderService $service)
    {
        $n = $service->scanWorkspace($request->user(), $workspace);

        return back()->with('success', 'Đã xếp '.$n.' lời nhắc mới vào queue; chưa đồng nghĩa đã gửi.');
    }

    public function operations(Request $request, Workspace $workspace, QueueObservation $service)
    {
        return view('utilities.operations', ['workspace' => $workspace] + $service->data($request->user(), $workspace));
    }
}
