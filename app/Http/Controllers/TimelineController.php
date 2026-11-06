<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Workspace;
use App\Services\Calendar\CalendarViewService;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function index(Request $request, Workspace $workspace, Project $project, CalendarViewService $views)
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
        $this->authorize('view', $project);
        $range = $views->range($request);

        return view('calendar.timeline', ['workspace' => $workspace, 'project' => $project, 'range' => $range, 'groups' => $views->timeline($request->user(), $workspace, $project, $range),
            'navigation' => $views->presentation($request, $range), 'schedule' => config('work_schedule')]);
    }
}
