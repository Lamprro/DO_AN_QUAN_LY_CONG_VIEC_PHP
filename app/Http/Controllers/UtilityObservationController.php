<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Queue\QueueObservation;
use App\Services\Task\TaskReminderService;
use Illuminate\Http\Request;

class UtilityObservationController extends Controller
{
    public function reminders(Request $r, Workspace $workspace, TaskReminderService $service)
    {
        return view('utilities.reminders', ['workspace' => $workspace, 'reminders' => $service->visibleFor($r->user(), $workspace)]);
    }

    public function scan(Request $r, Workspace $workspace, TaskReminderService $service)
    {
        $n = $service->scanWorkspace($r->user(), $workspace);

        return back()->with('success', 'Đã xếp '.$n.' lời nhắc mới vào queue; chưa đồng nghĩa đã gửi.');
    }

    public function operations(Request $r, Workspace $workspace, QueueObservation $service)
    {
        return view('utilities.operations', ['workspace' => $workspace] + $service->data($r->user(), $workspace));
    }
}
