<?php

namespace App\Services\Report;

use App\Authorization\ReportAccess;
use App\Models\AiRun;
use App\Models\CalendarEvent;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\TaskBlocker;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Task\TaskTreeService;
use App\Services\Workspace\WorkspaceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(private ReportAccess $access, private TaskTreeService $trees, private WorkspaceService $workspaces) {}

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

        return [
            'project' => $project,
            'subject' => $subject,
            'start' => $start->utc(),
            'end' => $end->utc(),
            'timezone' => $actor->timezone,
            'include_archived' => (bool) ($data['include_archived'] ?? false),
        ];
    }

    public function minutes(array $intervals): float
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $seconds = 0;
        $left = null;
        $right = null;
        foreach ($intervals as [$start, $end]) {
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
            $filters = $this->filters($actor, $workspace, $data, $permission);
            $project = $filters['project'];
            $start = $filters['start'];
            $end = $filters['end'];
            $asOf = CarbonImmutable::now('UTC');
            $projectIds = $project ? [$project->id] : $this->access->projects($actor, $workspace)->pluck('id')->all();
            $tasks = Task::where('workspace_id', $workspace->id)
                ->where('task_type', 'WORK')
                ->where(fn ($q) => $q->whereIn('project_id', $projectIds)->when($workspace->kind->value === 'personal' && $workspace->owner_id === $actor->id, fn ($q) => $q->orWhereNull('project_id')))
                ->when($filters['subject'], fn ($q) => $q->where('assignee_id', $filters['subject']))
                ->when(! $filters['include_archived'], fn ($q) => $q->whereNull('archived_at'))
                ->with('assignee:id,name')
                ->orderBy('id')
                ->limit(10001)
                ->get();
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
            $events = CalendarEvent::whereIn('task_id', $activeIds)
                ->where('event_type', 'TASK_BLOCK')
                ->where('status', '!=', 'cancelled')
                ->where('starts_at', '<', $end)
                ->where('ends_at', '>', $start)
                ->with(['workspace', 'project', 'task'])
                ->orderBy('id')
                ->limit(20001)
                ->get();
            $entries = TimeEntry::whereIn('task_id', $ids)
                ->where('started_at', '<', $end)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $start))
                ->orderBy('id')
                ->limit(20001)
                ->get();
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
                if (! Gate::forUser($actor)->allows('view', $event) || $filters['subject'] && $event->user_id !== $filters['subject']) {
                    continue;
                }
                $planned[$event->user_id][] = [max($start->timestamp, $event->starts_at->timestamp), min($end->timestamp, $event->ends_at->timestamp)];
                $eventSources[] = $event->id;
                $calendarProjects[] = $event->project_id;
            }
            foreach ($entries as $entry) {
                if ($filters['subject'] && $entry->user_id !== $filters['subject']) {
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
                $workload[$userId] = [
                    'user_id' => $userId === 'unassigned' ? null : $userId,
                    'name' => $group->first()->assignee?->name ?? 'Chưa phân công',
                    'tasks' => $group->count(),
                    'estimate_minutes' => $group->sum('estimate_minutes'),
                    'estimate_unknown' => $group->whereNull('estimate_minutes')->count(),
                    'planned_minutes' => $this->minutes($planned[$userId] ?? []),
                    'actual_minutes' => isset($actual[$userId]) ? $this->minutes($actual[$userId]) : null,
                ];
            }
            // A calendar/session owner can differ from the current assignee after reassignment.
            foreach (array_unique(array_merge(array_keys($planned), array_keys($actual))) as $userId) {
                if (! isset($workload[$userId])) {
                    $workload[$userId] = [
                        'user_id' => $userId,
                        'name' => User::whereKey($userId)->value('name') ?? 'Thành viên',
                        'tasks' => 0,
                        'estimate_minutes' => 0,
                        'estimate_unknown' => 0,
                        'planned_minutes' => $this->minutes($planned[$userId] ?? []),
                        'actual_minutes' => isset($actual[$userId]) ? $this->minutes($actual[$userId]) : null,
                    ];
                }
            }
            $blockerRows = TaskBlocker::whereIn('task_id', $activeIds)->where('status', 'open')->get(['id', 'task_id']);

            return [
                'schema_version' => 1,
                'workspace_id' => $workspace->id,
                'workspace_name' => $workspace->name,
                'generated_at' => $asOf->toIso8601String(),
                'timezone' => $filters['timezone'],
                'period' => [
                    'from' => $data['from'],
                    'to' => $data['to'],
                    'start_utc' => $start->toIso8601String(),
                    'end_utc' => $end->toIso8601String(),
                ],
                'filters' => [
                    'project_id' => $project?->id,
                    'project_name' => $project?->name,
                    'subject_user_id' => $filters['subject'],
                    'include_archived' => $filters['include_archived'],
                ],
                'access' => [
                    'project_ids' => $tasks->pluck('project_id')->filter()->unique()->values()->all(),
                    'personal_tasks' => $tasks->contains(fn ($t) => $t->project_id === null),
                    'calendar_project_ids' => array_values(array_unique($calendarProjects, SORT_REGULAR)),
                ],
                'inventory' => [
                    'work_count' => $tasks->count(),
                    'status_counts' => $statuses,
                    'overdue' => $active->filter(fn ($t) => $t->status->value !== 'DONE' && $t->deadline_at && $t->deadline_at->lt($asOf))->count(),
                    'open_blockers' => $blockerRows->count(),
                    'blocked_tasks' => $blockerRows->pluck('task_id')->unique()->count(),
                    'estimate_minutes' => $active->sum('estimate_minutes'),
                    'estimate_unknown' => $active->whereNull('estimate_minutes')->count(),
                    'progress' => $this->trees->progress($tasks),
                ],
                'period_metrics' => [
                    'completed_work' => $active->filter(fn ($t) => $t->status->value === 'DONE' && $t->completed_at && $t->completed_at->gte($start) && $t->completed_at->lt($end))->count(),
                    'planned_minutes' => array_sum(array_map(fn ($slots) => $this->minutes($slots), $planned)),
                    'actual_minutes' => $actual ? array_sum(array_map(fn ($slots) => $this->minutes($slots), $actual)) : null,
                    'running_entries_excluded' => $running,
                ],
                'workload' => array_values($workload),
                'sources' => [
                    'task_ids' => $ids->all(),
                    'calendar_event_ids' => $eventSources,
                    'time_entry_ids' => $entrySources,
                    'blocker_ids' => $blockerRows->pluck('id')->all(),
                ],
                'definitions' => [
                    'inventory' => 'Trạng thái hiện tại lúc tạo/xem; kỳ không tái dựng trạng thái quá khứ. Chỉ WORK, mặc định loại archived; CANCELLED có bộ đếm riêng, không cộng estimate/progress/planned; phiên thực tế đã ghi vẫn được giữ.',
                    'period' => 'Khoảng UTC [start,end) từ ngày/múi giờ người tạo. Completed dùng completed_at; lịch/phiên cắt theo giao với kỳ, gộp khoảng chồng theo từng người.',
                    'member_filter' => 'Bộ lọc thành viên dùng người đang được giao WORK; thời lượng còn lọc chủ lịch/phiên. Không tái dựng phân công quá khứ.',
                    'actual' => 'Chỉ time_entries đã kết thúc; null nghĩa chưa có dữ liệu trong kỳ. Không suy từ status/estimate. Planned chỉ gồm TASK_BLOCK người xem được quyền đọc.',
                ],
                'work_schedule' => config('work_schedule'),
            ];
        });
    }

    public function snapshot(User $actor, Workspace $workspace, array $data): ReportSnapshot
    {
        return DB::transaction(function () use ($actor, $workspace, $data) {
            $metrics = $this->metrics($actor, $workspace, $data, 'report.create');
            $projectId = $metrics['filters']['project_id'];
            $subject = $metrics['filters']['subject_user_id'];

            return ReportSnapshot::create([
                'workspace_id' => $workspace->id,
                'project_id' => $projectId,
                'subject_user_id' => $subject,
                'generated_by' => $actor->id,
                'scope' => $projectId ? 'project' : ($subject ? 'personal' : 'team'),
                'period_start' => $metrics['period']['start_utc'],
                'period_end' => $metrics['period']['end_utc'],
                'metrics_json' => $metrics,
            ]);
        });
    }

    /** Live dashboard; ReportService::metrics asserts report.view on the scope. */
    public function dashboard(User $actor, Workspace $workspace, array $filters): array
    {
        $metrics = $this->metrics($actor, $workspace, $filters);
        $project = empty($filters['project_id']) ? null : $workspace->projects()->findOrFail($filters['project_id']);

        return [
            'workspace' => $workspace,
            'metrics' => $metrics,
            'filters' => $filters,
        ] + $this->formOptions($actor, $workspace) + ['canCreate' => $this->access->scope($actor, $workspace, $project, 'report.create')];
    }

    /** Form for an AI commentary request; asserts report.create on the scope. */
    public function aiRunForm(User $actor, Workspace $workspace, array $filters): array
    {
        $this->filters($actor, $workspace, $filters, 'report.create');

        return ['workspace' => $workspace, 'filters' => $filters] + $this->formOptions($actor, $workspace);
    }

    private function formOptions(User $actor, Workspace $workspace): array
    {
        return [
            'projects' => $this->access->projects($actor, $workspace),
            'members' => $this->workspaces->activeMembers($workspace),
        ];
    }

    public function snapshots(User $actor, Workspace $workspace, array $filters, string $path, array $query): LengthAwarePaginator
    {
        $rows = ReportSnapshot::where('workspace_id', $workspace->id)
            ->where('generated_by', $actor->id)
            ->where('metrics_json->schema_version', 1)
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id));

        return $this->visiblePage($actor, $this->createdBetween($rows, $actor, $filters), $filters, $path, $query, 'Có quá nhiều bản báo cáo; lọc project hoặc ngày tạo.');
    }

    public function aiRuns(User $actor, Workspace $workspace, array $filters, string $path, array $query): LengthAwarePaginator
    {
        $rows = AiRun::where('workspace_id', $workspace->id)
            ->where('requested_by', $actor->id)
            ->where('context_snapshot->schema_version', 1)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        return $this->visiblePage($actor, $this->createdBetween($rows, $actor, $filters), $filters, $path, $query, 'Có quá nhiều yêu cầu; lọc trạng thái hoặc ngày tạo.');
    }

    /** CSV rows of a frozen snapshot (formula-leading cells are escaped by the caller). */
    public function csvRows(array $m): array
    {
        $rows = [
            ['Workspace', $m['workspace_name']],
            ['Kỳ [from,to)', $m['period']['from'], $m['period']['to'], $m['timezone']],
            ['Lúc tạo', $m['generated_at']],
        ];
        foreach ($m['inventory']['status_counts'] as $status => $number) {
            $rows[] = ['WORK '.$status, $number];
        }
        $rows[] = ['Quá hạn hiện tại', $m['inventory']['overdue']];
        $rows[] = ['Hoàn thành trong kỳ', $m['period_metrics']['completed_work']];
        $rows[] = ['Giờ dự kiến (phút)', $m['period_metrics']['planned_minutes']];
        $rows[] = ['Giờ thực tế (phút)', $m['period_metrics']['actual_minutes'] ?? 'Chưa có dữ liệu'];
        $rows[] = [
            'Thành viên',
            'WORK không hủy',
            'Ước lượng phút',
            'Chưa ước lượng',
            'Dự kiến phút',
            'Thực tế phút',
        ];
        foreach ($m['workload'] as $person) {
            $rows[] = [
                $person['name'],
                $person['tasks'],
                $person['estimate_minutes'],
                $person['estimate_unknown'],
                $person['planned_minutes'],
                $person['actual_minutes'] ?? 'Chưa có dữ liệu',
            ];
        }
        foreach ($m['definitions'] as $definition) {
            $rows[] = ['Cách tính', $definition];
        }

        return $rows;
    }

    private function createdBetween(Builder $rows, User $actor, array $filters): Builder
    {
        return $rows->when($filters['created_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', CarbonImmutable::parse($date, $actor->timezone)->utc()))->when($filters['created_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<', CarbonImmutable::parse($date, $actor->timezone)->utc()));
    }

    private function visiblePage(User $actor, Builder $rows, array $filters, string $path, array $query, string $tooMany): LengthAwarePaginator
    {
        $rows = $rows->orderByDesc('created_at')->orderByDesc('id')->limit(1001)->get();
        if ($rows->count() > 1000) {
            throw ValidationException::withMessages(['created_from' => $tooMany]);
        }
        $visible = $rows->filter(fn ($row) => Gate::forUser($actor)->allows('view', $row))->values();
        $page = (int) ($filters['page'] ?? 1);

        return new LengthAwarePaginator($visible->forPage($page, 20)->values(), $visible->count(), 20, $page, ['path' => $path, 'query' => $query]);
    }
}
