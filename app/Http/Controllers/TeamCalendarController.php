<?php

namespace App\Http\Controllers;

use App\Models\CalendarProposal;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Services\TeamCalendarService;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;

class TeamCalendarController extends Controller
{
    public function index(Request $r, Workspace $workspace, TeamCalendarService $service)
    {
        $data = $r->validate(['project_id' => 'nullable|uuid', 'focus' => 'nullable|uuid']);
        $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);
        abort_unless($service->allowed($r->user(), $workspace, $project), 403);
        $proposals = CalendarProposal::when($data['focus'] ?? null, fn ($q, $id) => $q->whereKey($id))->where('workspace_id', $workspace->id)->where('project_id', $project?->id)->when(! app(TaskPolicy::class)->scope($r->user(), $workspace, $project, 'task.view'), fn ($q) => $q->whereNull('task_id'))->where(fn ($q) => $q->where('recipient_id', $r->user()->id)->orWhere('proposed_by', $r->user()->id))->with('recipient', 'proposer', 'event')->latest()->paginate(15)->withQueryString();
        $people = app(ProjectAccess::class)->eligibleMembers($workspace, $project, 'calendar.view')->filter(fn ($m) => $service->allowed($m->user, $workspace, $project, 'calendar.create'));
        $tasks = $workspace->tasks()->where('project_id', $project?->id)->where('task_type', 'WORK')->whereNull('archived_at')->whereNotIn('status', ['DONE', 'CANCELLED'])->orderBy('title')->get()->filter(fn ($task) => app(TaskPolicy::class)->view($r->user(), $task));

        return view('calendar.team', compact('workspace', 'project', 'proposals', 'people', 'tasks') + ['canPropose' => $service->allowed($r->user(), $workspace, $project, 'calendar.create')]);
    }

    public function store(Request $r, Workspace $workspace, TeamCalendarService $service)
    {
        $p = $service->propose($r->user(), $workspace, $r->all());

        return to_route('workspaces.calendar.team', [$workspace, 'project_id' => $p->project_id])->with('success', 'Đã gửi đề xuất. Chỉ tạo lịch khi người nhận chấp thuận.');
    }

    public function respond(Request $r, Workspace $workspace, string $proposal, string $action, TeamCalendarService $service)
    {
        $data = $r->validate(['revision' => 'required|integer|min:1']);
        $p = CalendarProposal::where('workspace_id', $workspace->id)->findOrFail($proposal);
        $service->respond($r->user(), $p, $action, (int) $data['revision']);

        return back()->with('success', 'Đã xử lý đề xuất lịch.');
    }
}
