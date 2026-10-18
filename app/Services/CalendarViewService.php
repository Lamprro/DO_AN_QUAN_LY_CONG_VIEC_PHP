<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\CalendarEventPolicy;
use App\Support\ProjectAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CalendarViewService
{
    public function __construct(private CalendarEventPolicy $policy, private ProjectAccess $projects) {}

    public function range(Request $request): array
    {
        $data = $request->validate(['view' => ['sometimes', 'in:day,month,week,list'], 'date' => ['sometimes', 'date_format:Y-m-d'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'], 'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d'], 'include_cancelled' => ['sometimes', 'boolean']]);
        $timezone = $request->user()->timezone;
        $date = isset($data['date']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], $timezone) : CarbonImmutable::now($timezone)->startOfDay();
        $mode = $data['view'] ?? 'week';
        $start = $mode === 'month' ? $date->startOfMonth() : ($mode === 'week' ? $date->startOfWeek() : $date);
        $end = $mode === 'month' ? $start->addMonth() : $start->addDays($mode === 'day' ? 1 : ($mode === 'week' ? 7 : 30));
        if (! empty($data['from'])) {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], $timezone);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], $timezone);
        }
        if ($end->lte($start) || $end->gt($start->addDays(93))) {
            throw ValidationException::withMessages(['to' => 'Phạm vi phải tăng, tối đa 93 ngày; ngày kết thúc không bao gồm.']);
        }
        $days = [];
        for ($day = $start; $day->lt($end); $day = $day->addDay()) {
            $days[] = $day->format('Y-m-d');
        }

        return ['mode' => $mode, 'timezone' => $timezone, 'start' => $start, 'end' => $end, 'days' => $days, 'includeCancelled' => (bool) ($data['include_cancelled'] ?? false)];
    }

    public function presentation(Request $request, array $range): array
    {
        $base = $request->only('view', 'project_id', 'include_cancelled');
        $base['view'] = $range['mode'];
        $link = function ($date, $from = null, $to = null) use ($request, $base) {
            return $request->url().'?'.http_build_query(array_filter($base + ['date' => $date, 'from' => $from, 'to' => $to], fn ($v) => $v !== null && $v !== ''));
        };
        if ($request->filled('from')) {
            $days = count($range['days']);
            $previous = $range['start']->subDays($days);
            $next = $range['end'];
            $prev = $link($previous->toDateString(), $previous->toDateString(), $range['start']->toDateString());
            $nextUrl = $link($next->toDateString(), $next->toDateString(), $next->addDays($days)->toDateString());
        } else {
            $previous = match ($range['mode']) {
                'month' => $range['start']->subMonth(),'week' => $range['start']->subWeek(),'day' => $range['start']->subDay(),default => $range['start']->subDays(30)
            };
            $next = match ($range['mode']) {
                'month' => $range['start']->addMonth(),'week' => $range['start']->addWeek(),'day' => $range['start']->addDay(),default => $range['end']
            };
            $prev = $link($previous->toDateString());
            $nextUrl = $link($next->toDateString());
        }
        $now = CarbonImmutable::now($range['timezone']);
        $span = $range['end']->timestamp - $range['start']->timestamp;
        $ticks = [];
        foreach ($range['days'] as $day) {
            $d = CarbonImmutable::parse($day, $range['timezone']);
            $ticks[] = ['date' => $day, 'label' => $d->format('d/m'), 'offset' => round(($d->timestamp - $range['start']->timestamp) * 100 / $span, 4), 'today' => $day === $now->toDateString()];
        }

        return ['previous' => $prev, 'next' => $nextUrl, 'today' => $link($now->toDateString()), 'todayDate' => $now->toDateString(), 'ticks' => $ticks, 'nowPercent' => $now->gte($range['start']) && $now->lt($range['end']) ? round(($now->timestamp - $range['start']->timestamp) * 100 / $span, 4) : null];
    }

    public function rows(User $actor, Workspace $workspace, array $range, ?Project $project = null): array
    {
        $workspaceCalendar = $this->policy->scope($actor, $workspace, null, 'calendar.view');
        $projectIds = $this->projects->visible($actor, $workspace)->get()->filter(fn ($p) => $this->policy->scope($actor, $workspace, $p, 'calendar.view'))->pluck('id');
        abort_unless($project ? $this->policy->scope($actor, $workspace, $project, 'calendar.view') : ($workspaceCalendar || $projectIds->isNotEmpty()), 403);
        $events = CalendarEvent::where('workspace_id', $workspace->id)
            ->when($project, fn ($q) => $q->where('project_id', $project->id), fn ($q) => $q->where(fn ($q) => $q->whereIn('project_id', $projectIds)->when($workspaceCalendar, fn ($q) => $q->orWhereNull('project_id'))))
            ->where('starts_at', '<', $range['end']->utc())->where('ends_at', '>', $range['start']->utc())
            ->when(! $range['includeCancelled'], fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->with(['user', 'workspace', 'project', 'task'])->orderBy('starts_at')->orderBy('id')->limit(1001)->get();
        if ($events->count() > 1000) {
            throw ValidationException::withMessages(['from' => 'Có quá nhiều sự kiện; chọn phạm vi ngày ngắn hơn.']);
        }
        $activeUsers = $workspace->members()->where('status', 'active')->whereHas('user', fn ($q) => $q->where('status', 'active'))->pluck('user_id');
        $rows = [];
        foreach ($events as $event) {
            if (! $activeUsers->contains($event->user_id)) {
                continue;
            }
            $canView = $this->policy->view($actor, $event);
            if (! $canView && (! $event->is_busy || $event->status->value === 'cancelled')) {
                continue;
            }
            $rows[] = ['id' => $canView ? $event->id : null, 'title' => $canView ? $event->title : 'Bận (riêng tư)',
                'description' => $canView ? $event->description : null, 'owner' => $canView ? $event->user->name : null,
                'type' => $canView ? $event->event_type->value : 'BUSY', 'status' => $canView ? $event->status->value : null,
                'starts_at' => $event->starts_at->setTimezone($range['timezone']), 'ends_at' => $event->ends_at->setTimezone($range['timezone']),
                'all_day' => $event->all_day, 'is_busy' => $event->is_busy,
                'task' => $canView ? $event->task : null, 'private' => ! $canView];
        }

        return $rows;
    }
}
