<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\TaskBlocker;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ReportAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(private ReportAccess $access, private TaskTreeService $trees) {}

    public function defaults(User $actor): array
    {
        $today = CarbonImmutable::now($actor->timezone);

        return ['from' => $today->startOfMonth()->format('Y-m-d'), 'to' => $today->startOfMonth()->addMonth()->format('Y-m-d'), 'project_id' => null, 'subject_user_id' => null, 'include_archived' => false];
    }

    public function filters(User $actor, Workspace $workspace, array $data, string $permission = 'report.view'): array
    {
        $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);
        abort_unless($this->access->scope($actor, $workspace, $project, $permission), 403);
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], $actor->timezone);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], $actor->timezone);
        if ($end->lte($start) || $end->gt($start->addYear())) {
            throw ValidationException::withMessages(['to' => 'Kỳ phải tăng, tối đa một năm; ngày cuối không bao gồm.']);
        }
        $subject = $data['subject_user_id'] ?? null;
        if ($subject && ! $workspace->members()->where('user_id', $subject)->where('status', 'active')->whereHas('user', fn ($q) => $q->where('status', 'active'))->exists()) {
            throw ValidationException::withMessages(['subject_user_id' => 'Chọn thành viên đang hoạt động trong workspace.']);
        }
        if ($workspace->kind->value === 'personal') {
            $subject = $actor->id;
        }

        return ['project' => $project, 'subject' => $subject, 'start' => $start->utc(), 'end' => $end->utc(), 'timezone' => $actor->timezone, 'include_archived' => (bool) ($data['include_archived'] ?? false)];
    }

    public function minutes(array $intervals): float
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $seconds = 0;
        $left = null;
        $right = null;
        foreach ($intervals as [$start,$end]) {
            if ($left === null) {
                $left = $start;
                $right = $end;
            } elseif ($start <= $right) {
                $right = max($right, $end);
            } else {
                $seconds += $right - $left;
                $left = $start;
                $right = $end;
            }
        }

        return round(($seconds + ($left === null ? 0 : $right - $left)) / 60, 2);
    }

    public function metrics(User $actor, Workspace $workspace, array $data, string $permission = 'report.view'): array
    {
        return DB::transaction(function () use ($actor, $workspace, $data, $permission) {
            $f = $this->filters($actor, $workspace, $data, $permission);
            $project = $f['project'];
            $start = $f['start'];
            $end = $f['end'];
            $asOf = CarbonImmutable::now('UTC');
            $projectIds = $project ? [$project->id] : $this->access->projects($actor, $workspace)->pluck('id')->all();
            $tasks = Task::where('workspace_id', $workspace->id)->where('task_type', 'WORK')
                ->where(fn ($q) => $q->whereIn('project_id', $projectIds)->when($workspace->kind->value === 'personal' && $workspace->owner_id === $actor->id, fn ($q) => $q->orWhereNull('project_id')))
                ->when($f['subject'], fn ($q) => $q->where('assignee_id', $f['subject']))->when(! $f['include_archived'], fn ($q) => $q->whereNull('archived_at'))
                ->with('assignee:id,name')->orderBy('id')->limit(10001)->get();
            if ($tasks->count() > 10000) {
                throw ValidationException::withMessages(['project_id' => 'Có quá nhiều task; chọn project hoặc thành viên cụ thể.']);
            }
            $ids = $tasks->pluck('id');
            $active = $tasks->filter(fn ($t) => $t->status->value !== 'CANCELLED');
            $activeIds = $active->pluck('id');
            $statuses = array_fill_keys(['TODO', 'IN_PROGRESS', 'REVIEW', 'DONE', 'CANCELLED'], 0);
            foreach ($tasks as $task) {
                $statuses[$task->status->value]++;
            }
            $events = CalendarEvent::whereIn('task_id', $activeIds)->where('event_type', 'TASK_BLOCK')->where('status', '!=', 'cancelled')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->with(['workspace', 'project', 'task'])->orderBy('id')->limit(20001)->get();
            $entries = TimeEntry::whereIn('task_id', $ids)->where('started_at', '<', $end)->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $start))->orderBy('id')->limit(20001)->get();
            if ($events->count() > 20000 || $entries->count() > 20000) {
                throw ValidationException::withMessages(['from' => 'Có quá nhiều phiên/lịch; thu hẹp kỳ hoặc phạm vi.']);
            }
            $planned = [];
            $actual = [];
            $eventSources = [];
            $entrySources = [];
            $calendarProjects = [];
            $running = 0;
            foreach ($events as $event) {
                if (! Gate::forUser($actor)->allows('view', $event) || ($f['subject'] && $event->user_id !== $f['subject'])) {
                    continue;
                }
                $planned[$event->user_id][] = [max($start->timestamp, $event->starts_at->timestamp), min($end->timestamp, $event->ends_at->timestamp)];
                $eventSources[] = $event->id;
                $calendarProjects[] = $event->project_id;
            }
            foreach ($entries as $entry) {
                if ($f['subject'] && $entry->user_id !== $f['subject']) {
                    continue;
                }
                if (! $entry->ended_at) {
                    $running++;

                    continue;
                }
                $actual[$entry->user_id][] = [max($start->timestamp, $entry->started_at->timestamp), min($end->timestamp, $entry->ended_at->timestamp)];
                $entrySources[] = $entry->id;
            }
            $workload = [];
            foreach ($active->groupBy(fn ($t) => $t->assignee_id ?? 'unassigned') as $userId => $group) {
                $workload[$userId] = ['user_id' => $userId === 'unassigned' ? null : $userId, 'name' => $group->first()->assignee?->name ?? 'Chưa phân công', 'tasks' => $group->count(),
                    'estimate_minutes' => $group->sum('estimate_minutes'), 'estimate_unknown' => $group->whereNull('estimate_minutes')->count(), 'planned_minutes' => $this->minutes($planned[$userId] ?? []), 'actual_minutes' => isset($actual[$userId]) ? $this->minutes($actual[$userId]) : null];
            }
            // A calendar/session owner can differ from the current assignee after reassignment.
            foreach (array_unique(array_merge(array_keys($planned), array_keys($actual))) as $userId) {
                if (! isset($workload[$userId])) {
                    $workload[$userId] = ['user_id' => $userId, 'name' => User::whereKey($userId)->value('name') ?? 'Thành viên', 'tasks' => 0, 'estimate_minutes' => 0, 'estimate_unknown' => 0, 'planned_minutes' => $this->minutes($planned[$userId] ?? []), 'actual_minutes' => isset($actual[$userId]) ? $this->minutes($actual[$userId]) : null];
                }
            }
            $blockerRows = TaskBlocker::whereIn('task_id', $activeIds)->where('status', 'open')->get(['id', 'task_id']);

            return ['schema_version' => 1, 'workspace_id' => $workspace->id, 'workspace_name' => $workspace->name, 'generated_at' => $asOf->toIso8601String(),
                'timezone' => $f['timezone'], 'period' => ['from' => $data['from'], 'to' => $data['to'], 'start_utc' => $start->toIso8601String(), 'end_utc' => $end->toIso8601String()],
                'filters' => ['project_id' => $project?->id, 'project_name' => $project?->name, 'subject_user_id' => $f['subject'], 'include_archived' => $f['include_archived']],
                'access' => ['project_ids' => $tasks->pluck('project_id')->filter()->unique()->values()->all(), 'personal_tasks' => $tasks->contains(fn ($t) => $t->project_id === null), 'calendar_project_ids' => array_values(array_unique($calendarProjects, SORT_REGULAR))],
                'inventory' => ['work_count' => $tasks->count(), 'status_counts' => $statuses, 'overdue' => $active->filter(fn ($t) => $t->status->value !== 'DONE' && $t->deadline_at && $t->deadline_at->lt($asOf))->count(),
                    'open_blockers' => $blockerRows->count(), 'blocked_tasks' => $blockerRows->pluck('task_id')->unique()->count(), 'estimate_minutes' => $active->sum('estimate_minutes'), 'estimate_unknown' => $active->whereNull('estimate_minutes')->count(), 'progress' => $this->trees->progress($tasks)],
                'period_metrics' => ['completed_work' => $active->filter(fn ($t) => $t->status->value === 'DONE' && $t->completed_at && $t->completed_at->gte($start) && $t->completed_at->lt($end))->count(),
                    'planned_minutes' => array_sum(array_map(fn ($slots) => $this->minutes($slots), $planned)), 'actual_minutes' => $actual ? array_sum(array_map(fn ($slots) => $this->minutes($slots), $actual)) : null, 'running_entries_excluded' => $running],
                'workload' => array_values($workload), 'sources' => ['task_ids' => $ids->all(), 'calendar_event_ids' => $eventSources, 'time_entry_ids' => $entrySources, 'blocker_ids' => $blockerRows->pluck('id')->all()],
                'definitions' => ['inventory' => 'Trạng thái hiện tại lúc tạo/xem; kỳ không tái dựng trạng thái quá khứ. Chỉ WORK, mặc định loại archived; CANCELLED có bộ đếm riêng, không cộng estimate/progress/planned; phiên thực tế đã ghi vẫn được giữ.',
                    'period' => 'Khoảng UTC [start,end) từ ngày/múi giờ người tạo. Completed dùng completed_at; lịch/phiên cắt theo giao với kỳ, gộp khoảng chồng theo từng người.',
                    'member_filter' => 'Bộ lọc thành viên dùng người đang được giao WORK; thời lượng còn lọc chủ lịch/phiên. Không tái dựng phân công quá khứ.',
                    'actual' => 'Chỉ time_entries đã kết thúc; null nghĩa chưa có dữ liệu trong kỳ. Không suy từ status/estimate. Planned chỉ gồm TASK_BLOCK người xem được quyền đọc.'],
                'work_schedule' => config('work_schedule')];
        });
    }

    public function snapshot(User $actor, Workspace $workspace, array $data): ReportSnapshot
    {
        return DB::transaction(function () use ($actor, $workspace, $data) {
            $metrics = $this->metrics($actor, $workspace, $data, 'report.create');
            $projectId = $metrics['filters']['project_id'];
            $subject = $metrics['filters']['subject_user_id'];

            return ReportSnapshot::create(['workspace_id' => $workspace->id, 'project_id' => $projectId, 'subject_user_id' => $subject, 'generated_by' => $actor->id,
                'scope' => $projectId ? 'project' : ($subject ? 'personal' : 'team'), 'period_start' => $metrics['period']['start_utc'], 'period_end' => $metrics['period']['end_utc'], 'metrics_json' => $metrics]);
        });
    }
}
