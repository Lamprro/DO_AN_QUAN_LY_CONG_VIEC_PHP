<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Workspace;
use App\Policies\CalendarEventPolicy;
use App\Policies\TaskPolicy;
use App\Services\CalendarService;
use App\Services\CalendarViewService;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CalendarEventController extends Controller
{
    public function __construct(private CalendarService $service, private CalendarViewService $views, private CalendarEventPolicy $policy, private ProjectAccess $projects) {}

    public function index(Request $request, Workspace $workspace)
    {
        $range = $this->views->range($request);
        $request->validate(['project_id' => 'nullable|uuid']);
        $project = $request->filled('project_id') ? $workspace->projects()->findOrFail($request->query('project_id')) : null;
        $rows = $this->views->rows($request->user(), $workspace, $range, $project);
        $navigation = $this->views->presentation($request, $range);

        return view('calendar.index', compact('workspace', 'project', 'range', 'rows', 'navigation') + ['canCreate' => $project ? app(TaskPolicy::class)->projectWritable($project) && $this->policy->scope($request->user(), $workspace, $project, 'calendar.create') : $this->canCreate($request, $workspace), 'schedule' => config('work_schedule')]);
    }

    private function canCreate(Request $request, Workspace $workspace): bool
    {
        if ($this->policy->scope($request->user(), $workspace, null, 'calendar.create')) {
            return true;
        }

        return $this->projects->visible($request->user(), $workspace)->get()->contains(fn ($project) => app(TaskPolicy::class)->projectWritable($project) && $this->policy->scope($request->user(), $workspace, $project, 'calendar.create'));
    }

    private function bound(Workspace $workspace, CalendarEvent $event): void
    {
        abort_unless($event->workspace_id === $workspace->id, 404);
    }

    private function context(Request $request, Workspace $workspace, ?CalendarEvent $event = null): array
    {
        $projects = $this->projects->visible($request->user(), $workspace)->get()->filter(fn ($p) => app(TaskPolicy::class)->projectWritable($p) && $this->policy->scope($request->user(), $workspace, $p, $event ? 'calendar.update' : 'calendar.create'));
        $tasks = $workspace->tasks()->where('assignee_id', $request->user()->id)->where('task_type', 'WORK')->whereIn('status', ['TODO', 'IN_PROGRESS', 'REVIEW'])->whereNull('archived_at')
            ->where(fn ($q) => $q->whereIn('project_id', $projects->pluck('id'))->orWhereNull('project_id'))->get()->filter(fn ($t) => app(TaskPolicy::class)->mutable($request->user(), $t, 'task.update'));
        if ($event?->task && ! $tasks->contains('id', $event->task_id)) {
            $tasks->push($event->task);
        }
        $members = $workspace->members()->where('status', 'active')->whereHas('user', fn ($q) => $q->where('status', 'active'))->with('user')->get();

        return compact('workspace', 'event', 'projects', 'tasks', 'members') + ['version' => $event ? $this->service->version($event) : null, 'timezones' => timezone_identifiers_list()];
    }

    public function create(Request $request, Workspace $workspace)
    {
        abort_unless($this->canCreate($request, $workspace), 403);

        $request->validate(['task_id' => 'nullable|uuid', 'project_id' => 'nullable|uuid']);
        $context = $this->context($request, $workspace);
        $prefill = [];
        if ($request->filled('project_id')) {
            abort_unless($context['projects']->contains('id', $request->query('project_id')), 403);
            $prefill['project_id'] = $request->query('project_id');
        }
        if ($request->filled('task_id')) {
            $task = $context['tasks']->firstWhere('id', $request->query('task_id'));
            abort_unless($task, 403);
            abort_if(isset($prefill['project_id']) && $prefill['project_id'] !== $task->project_id, 422);
            $prefill = ['task_id' => $task->id, 'project_id' => $task->project_id, 'title' => $task->title, 'event_type' => 'TASK_BLOCK'];
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
        $this->bound($workspace, $event);
        Gate::authorize('view', $event);

        return view('calendar.show', compact('workspace', 'event') + ['version' => $this->service->version($event), 'timezone' => $request->user()->timezone]);
    }

    public function edit(Request $request, Workspace $workspace, CalendarEvent $event)
    {
        $this->bound($workspace, $event);
        Gate::authorize('update', $event);

        if ($request->boolean('fresh')) {
            $request->session()->forget('_old_input');

            return redirect()->to($request->fullUrlWithoutQuery(['fresh']));
        }

        return view('calendar.form', $this->context($request, $workspace, $event));
    }

    public function update(CalendarEventRequest $request, Workspace $workspace, CalendarEvent $event)
    {
        $this->bound($workspace, $event);
        $this->service->save($request->user(), $workspace, $request->validated(), $event);

        return to_route('workspaces.calendar.show', [$workspace, $event])->with('success', 'Đã cập nhật lịch.');
    }

    public function destroy(Request $request, Workspace $workspace, CalendarEvent $event)
    {
        $this->bound($workspace, $event);
        $data = $request->validate(['version' => ['required', 'string', 'size:64']]);
        $this->service->delete($request->user(), $workspace, $event, $data['version']);

        return to_route('workspaces.calendar.index', $workspace)->with('success', 'Đã xóa sự kiện.');
    }
}
