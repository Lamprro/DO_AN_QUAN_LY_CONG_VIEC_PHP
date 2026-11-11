<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\Calendar\CalendarPageData;
use App\Services\Calendar\CalendarService;
use App\Services\Calendar\TeamCalendarService;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(private CalendarService $service, private CalendarPageData $views, private TeamCalendarService $proposals) {}

    public function index(Request $request, Workspace $workspace)
    {
        $range = $this->range($request);
        $request->validate(['project_id' => 'nullable|uuid']);
        $project = $request->filled('project_id') ? $workspace->projects()->findOrFail($request->query('project_id')) : null;
        $rows = $this->views->rows($request->user(), $workspace, $range, $project);
        $navigation = $this->views->presentation($request->url(), $request->all(), $range);

        return view('calendar.index', compact('workspace', 'project', 'range', 'rows', 'navigation') + [
            'canCreate' => $this->views->canCreate($request->user(), $workspace, $project),
            'schedule' => config('work_schedule'),
        ]);
    }

    public function create(Request $request, Workspace $workspace)
    {
        abort_unless($this->views->canCreate($request->user(), $workspace), 403);
        $request->validate(['task_id' => 'nullable|uuid', 'project_id' => 'nullable|uuid']);
        $context = $this->views->formContext($request->user(), $workspace);
        $prefill = [];
        if ($request->filled('project_id')) {
            abort_unless($context['projects']->contains('id', $request->query('project_id')), 403);
            $prefill['project_id'] = $request->query('project_id');
        }
        if ($request->filled('task_id')) {
            $task = $context['tasks']->firstWhere('id', $request->query('task_id'));
            abort_unless($task, 403);
            abort_if(isset($prefill['project_id']) && $prefill['project_id'] !== $task->project_id, 422);
            $prefill = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'title' => $task->title,
                'event_type' => 'TASK_BLOCK',
            ];
        }

        return view('calendar.form', $context + ['prefill' => $prefill]);
    }

    public function store(CalendarEventRequest $request, Workspace $workspace)
    {
        $event = $this->service->save($request->user(), $workspace, $request->validated());

        return to_route('workspaces.calendar.show', [$workspace, $event])->with('success', 'Đã tạo sự kiện.');
    }

    public function show(Request $request, Workspace $workspace, CalendarEvent $event)
    {
        $this->assertInWorkspace($workspace, $event);
        $this->authorize('view', $event);

        return view('calendar.show', compact('workspace', 'event') + ['version' => $this->service->version($event), 'timezone' => $request->user()->timezone]);
    }

    public function edit(Request $request, Workspace $workspace, CalendarEvent $event)
    {
        $this->assertInWorkspace($workspace, $event);
        $this->authorize('update', $event);
        if ($request->boolean('fresh')) {
            $request->session()->forget('_old_input');

            return redirect()->to($request->fullUrlWithoutQuery(['fresh']));
        }

        return view('calendar.form', $this->views->formContext($request->user(), $workspace, $event));
    }

    public function update(CalendarEventRequest $request, Workspace $workspace, CalendarEvent $event)
    {
        $this->assertInWorkspace($workspace, $event);
        $this->service->save($request->user(), $workspace, $request->validated(), $event);

        return to_route('workspaces.calendar.show', [$workspace, $event])->with('success', 'Đã cập nhật lịch.');
    }

    public function destroy(Request $request, Workspace $workspace, CalendarEvent $event)
    {
        $this->assertInWorkspace($workspace, $event);
        $data = $request->validate(['version' => ['required', 'string', 'size:64']]);
        $this->service->delete($request->user(), $workspace, $event, $data['version']);

        return to_route('workspaces.calendar.index', $workspace)->with('success', 'Đã xóa sự kiện.');
    }

    /** {event} is bound without scoping (see routes), so check the workspace here. */
    private function assertInWorkspace(Workspace $workspace, CalendarEvent $event): void
    {
        abort_unless($event->workspace_id === $workspace->id, 404);
    }

    public function timeline(Request $request, Workspace $workspace, Project $project, CalendarPageData $views)
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
        $this->authorize('view', $project);
        $range = $this->range($request);

        return view('calendar.timeline', [
            'workspace' => $workspace,
            'project' => $project,
            'range' => $range,
            'groups' => $views->timeline($request->user(), $workspace, $project, $range),
            'navigation' => $views->presentation($request->url(), $request->all(), $range),
            'schedule' => config('work_schedule'),
        ]);
    }

    public function team(Request $request, Workspace $workspace, CalendarPageData $views)
    {
        $data = $request->validate(['project_id' => 'nullable|uuid', 'focus' => 'nullable|uuid']);
        $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);

        return view('calendar.team', $views->team($request->user(), $workspace, $project, $data['focus'] ?? null));
    }

    public function propose(Request $request, Workspace $workspace)
    {
        $proposal = $this->proposals->propose($request->user(), $workspace, $request->all());

        return to_route('workspaces.calendar.team', [$workspace, 'project_id' => $proposal->project_id])->with('success', 'Đã gửi đề xuất. Chỉ tạo lịch khi người nhận chấp thuận.');
    }

    public function respond(Request $request, Workspace $workspace, string $proposal, string $action)
    {
        $data = $request->validate(['version' => 'required|string|size:64']);
        $this->proposals->respond($request->user(), $this->proposals->find($workspace, $proposal), $action, $data['version']);

        return back()->with('success', 'Đã xử lý đề xuất lịch.');
    }

    private function range(Request $request): array
    {
        $filters = $request->validate([
            'view' => ['sometimes', 'in:day,month,week,list'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d'],
            'include_cancelled' => ['sometimes', 'boolean'],
        ]);

        return $this->views->range($filters, $request->user()->timezone);
    }
}
