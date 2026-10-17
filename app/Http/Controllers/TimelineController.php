<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Workspace;
use App\Services\CalendarViewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TimelineController extends Controller
{
    public function index(Request $request, Workspace $workspace, Project $project, CalendarViewService $service)
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
        Gate::authorize('view', $project);
        $range = $service->range($request);
        $rows = array_values(array_filter($service->rows($request->user(), $workspace, $range, $project), fn ($r) => $r['type'] === 'TASK_BLOCK' && ! $r['private']));
        $span = $range['end']->timestamp - $range['start']->timestamp;
        foreach ($rows as &$row) {
            $start = max($row['starts_at']->timestamp, $range['start']->timestamp);
            $end = min($row['ends_at']->timestamp, $range['end']->timestamp);
            $row['offset_percent'] = round(($start - $range['start']->timestamp) * 100 / $span, 4);
            $row['width_percent'] = round(($end - $start) * 100 / $span, 4);
        }
        unset($row);
        $groups = collect($rows)->groupBy(fn ($r) => $r['task']?->id ?? 'none');

        return view('calendar.timeline', compact('workspace', 'project', 'range', 'groups') + ['schedule' => config('work_schedule')]);
    }
}
