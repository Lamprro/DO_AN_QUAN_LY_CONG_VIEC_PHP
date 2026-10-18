<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\Workspace;
use App\Services\QueueObservation;
use App\Services\TaskReminderService;
use App\Support\WorkspaceAccess;
use Illuminate\Http\Request;

class UtilityObservationController extends Controller
{
    private function check(Request $r, Workspace $workspace): void
    {
        abort_unless(app(WorkspaceAccess::class)->allows($r->user(), $workspace, 'workspace.view'), 403);
    }

    public function reminders(Request $r, Workspace $workspace)
    {
        $this->check($r, $workspace);
        $reminders = TaskReminder::with('task')->where('workspace_id', $workspace->id)->where('user_id', $r->user()->id)->latest()->limit(100)->get()->filter(fn ($x) => $r->user()->can('view', Task::findOrFail($x->task_id)))->take(50);

        return view('utilities.reminders', compact('workspace', 'reminders'));
    }

    public function scan(Request $r, Workspace $workspace, TaskReminderService $service)
    {
        $this->check($r, $workspace);
        $n = $service->scan($r->user(), $workspace);

        return back()->with('success', 'Đã xếp '.$n.' lời nhắc mới vào queue; chưa đồng nghĩa đã gửi.');
    }

    public function operations(Request $r, Workspace $workspace, QueueObservation $service)
    {
        return view('utilities.operations', ['workspace' => $workspace] + $service->data($r->user(), $workspace));
    }
}
