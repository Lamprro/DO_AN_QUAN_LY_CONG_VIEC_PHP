<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Calendar\CalendarViewService;
use App\Services\Calendar\TeamCalendarService;
use Illuminate\Http\Request;

class TeamCalendarController extends Controller
{
    public function __construct(private TeamCalendarService $service) {}

    public function index(Request $r, Workspace $workspace, CalendarViewService $views)
    {
        $data = $r->validate(['project_id' => 'nullable|uuid', 'focus' => 'nullable|uuid']);
        $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);

        return view('calendar.team', $views->team($r->user(), $workspace, $project, $data['focus'] ?? null));
    }

    public function store(Request $r, Workspace $workspace)
    {
        $p = $this->service->propose($r->user(), $workspace, $r->all());

        return to_route('workspaces.calendar.team', [$workspace, 'project_id' => $p->project_id])->with('success', 'Đã gửi đề xuất. Chỉ tạo lịch khi người nhận chấp thuận.');
    }

    public function respond(Request $r, Workspace $workspace, string $proposal, string $action)
    {
        $data = $r->validate(['revision' => 'required|integer|min:1']);
        $this->service->respond($r->user(), $this->service->find($workspace, $proposal), $action, (int) $data['revision']);

        return back()->with('success', 'Đã xử lý đề xuất lịch.');
    }
}
