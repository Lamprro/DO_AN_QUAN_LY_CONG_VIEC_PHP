<?php

namespace App\Services;

use App\Enums\BlockerStatus;
use App\Models\CalendarEvent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public function allowed(User $actor, Workspace $workspace, ?Project $project, string $permission = 'plan.view'): bool
    {
        return app(TaskPolicy::class)->scope($actor, $workspace, $project, $permission);
    }

    private function fail(string $text): never
    {
        throw ValidationException::withMessages(['plan' => $text]);
    }

    public function fingerprint(User $actor, Workspace $workspace, ?Project $project): string
    {
        // Hash only: never publish other users' private calendar details in a plan.
        $tasks = $workspace->tasks()->where('project_id', $project?->id)->orderBy('id')->get();
        $ids = $tasks->pluck('id');
        $data = [$tasks->map(fn ($t) => $t->getRawOriginal())->all(),
            DB::table('task_dependencies')->whereIn('successor_task_id', $ids)->orderBy('id')->get()->toArray(),
            DB::table('task_blockers')->whereIn('task_id', $ids)->orderBy('id')->get()->toArray(),
            DB::table('time_entries')->whereIn('task_id', $ids)->orderBy('id')->get()->toArray(),
            DB::table('workspace_members')->where('workspace_id', $workspace->id)->orderBy('id')->get()->toArray(),
            DB::table('roles')->where('workspace_id', $workspace->id)->orderBy('id')->get()->toArray(),
            $project?->fresh()->getRawOriginal(),
            $project ? DB::table('project_members')->where('project_id', $project->id)->orderBy('id')->get()->toArray() : [],
            CalendarEvent::where('user_id', $actor->id)->orderBy('id')->get()->map(fn ($e) => $e->getRawOriginal())->all(),
            app(TimeTrackingService::class)->workHoursValues($actor, $workspace->timezone)];

        return hash('sha256', json_encode($data));
    }

    public function validate(array $input, ?string $timezone = null): array
    {
        $timezone ??= config('work_schedule.timezone');
        $data = Validator::make($input, [
            'schema_version' => 'required|integer|in:1', 'tasks' => 'required|array|list|min:1|max:100',
            'tasks.*' => 'required|array:ref,task_id,title,description,task_type,parent_ref,priority,estimate_minutes,assignee_id,reviewer_id',
            'tasks.*.ref' => 'required|string|regex:/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/|distinct',
            'tasks.*.task_id' => 'nullable|uuid', 'tasks.*.title' => 'required|string|max:180',
            'tasks.*.description' => 'nullable|string|max:10000', 'tasks.*.task_type' => 'required|in:GROUP,WORK,MILESTONE',
            'tasks.*.parent_ref' => 'nullable|string|max:40', 'tasks.*.priority' => 'required|in:low,medium,high,urgent',
            'tasks.*.estimate_minutes' => 'nullable|integer|min:0|max:525600',
            'tasks.*.assignee_id' => 'nullable|uuid', 'tasks.*.reviewer_id' => 'nullable|uuid',
            'dependencies' => 'present|array|list|max:300', 'dependencies.*' => 'array:predecessor,successor,lag_minutes',
            'dependencies.*.predecessor' => 'required|string', 'dependencies.*.successor' => 'required|string',
            'dependencies.*.lag_minutes' => 'required|integer|min:0|max:525600',
            'slots' => 'present|array|list|max:300', 'slots.*' => 'array:task_ref,starts_at,ends_at',
            'slots.*.task_ref' => 'required|string', 'slots.*.starts_at' => 'required|date_format:Y-m-d\TH:i',
            'slots.*.ends_at' => 'required|date_format:Y-m-d\TH:i',
        ])->validate();
        if (array_diff(array_keys($input), ['schema_version', 'tasks', 'dependencies', 'slots'])) {
            $this->fail('JSON có trường không được hỗ trợ.');
        }
        $refs = array_column($data['tasks'], null, 'ref');
        $used = [];
        foreach ($refs as $ref => $row) {
            if (! empty($row['task_id'])) {
                if (isset($used[$row['task_id']])) {
                    $this->fail('Một task chỉ xuất hiện một lần.');
                } $used[$row['task_id']] = true;
            }
            $parent = $row['parent_ref'] ?? null;
            $seen = [$ref => true];
            $depth = 1;
            while ($parent) {
                if (! isset($refs[$parent]) || isset($seen[$parent]) || $refs[$parent]['task_type'] !== 'GROUP' || ++$depth > 3) {
                    $this->fail('Cây task phải có GROUP cha, không vòng, tối đa 3 tầng.');
                }
                $seen[$parent] = true;
                $parent = $refs[$parent]['parent_ref'] ?? null;
            }
            if ($row['task_type'] !== 'WORK' && isset($row['estimate_minutes'])) {
                $this->fail('Chỉ WORK có ước lượng.');
            }
            if ($row['task_type'] === 'GROUP' && (! empty($row['assignee_id']) || ! empty($row['reviewer_id']))) {
                $this->fail('Không phân công trực tiếp GROUP.');
            }
        }
        $graph = [];
        $edges = [];
        foreach ($data['dependencies'] as $edge) {
            $a = $edge['predecessor'];
            $b = $edge['successor'];
            if (! isset($refs[$a],$refs[$b]) || $a === $b || $refs[$a]['task_type'] === 'GROUP' || $refs[$b]['task_type'] === 'GROUP' || isset($edges[$a.':'.$b])) {
                $this->fail('Dependency không hợp lệ hoặc trùng.');
            }
            $graph[$a][] = $b;
            $edges[$a.':'.$b] = true;
        }
        $colors = [];
        $visit = function ($node) use (&$visit, $graph, &$colors) {
            if (($colors[$node] ?? 0) === 1) {
                $this->fail('Dependency có vòng lặp.');
            }if (($colors[$node] ?? 0) === 2) {
                return;
            }$colors[$node] = 1;
            foreach ($graph[$node] ?? [] as $next) {
                $visit($next);
            }$colors[$node] = 2;
        };
        foreach (array_keys($refs) as $ref) {
            $visit($ref);
        }
        foreach ($data['slots'] as $slot) {
            if (! isset($refs[$slot['task_ref']]) || $refs[$slot['task_ref']]['task_type'] !== 'WORK') {
                $this->fail('Slot chỉ tham chiếu WORK trong bản nháp.');
            }
        }

        $slotBounds = [];
        foreach ($data['slots'] as $slot) {
            if ($slot['ends_at'] <= $slot['starts_at']) {
                $this->fail('Slot phải kết thúc sau khi bắt đầu.');
            }
            app(CalendarService::class)->localTime($slot['starts_at'], $timezone, false);
            app(CalendarService::class)->localTime($slot['ends_at'], $timezone, false);
            $ref = $slot['task_ref'];
            $slotBounds[$ref] = ['start' => isset($slotBounds[$ref]) ? min($slotBounds[$ref]['start'], $slot['starts_at']) : $slot['starts_at'],
                'end' => isset($slotBounds[$ref]) ? max($slotBounds[$ref]['end'], $slot['ends_at']) : $slot['ends_at']];
        }
        foreach ($data['dependencies'] as $edge) {
            $a = $slotBounds[$edge['predecessor']] ?? null;
            $b = $slotBounds[$edge['successor']] ?? null;
            if ($a && $b && app(CalendarService::class)->localTime($b['start'], $timezone, false)->lt(app(CalendarService::class)->localTime($a['end'], $timezone, false)->addMinutes($edge['lag_minutes']))) {
                $this->fail('Slot successor phải sau slot predecessor và đủ lag FS.');
            }
        }

        return $data;
    }

    public function create(User $actor, Workspace $workspace, ?Project $project, string $name, array $input, ?Plan $base = null, ?string $aiRunId = null): Plan
    {
        return DB::transaction(function () use ($actor, $workspace, $project, $name, $input, $base, $aiRunId) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            abort_unless($this->allowed($actor, $workspace, $project, 'plan.create') && app(TaskPolicy::class)->projectWritable($project), 403);
            $payload = $this->validate($input, $workspace->timezone);
            $group = (string) Str::uuid();
            $version = 1;
            $active = null;
            if ($base) {
                $base = Plan::lockForUpdate()->findOrFail($base->id);
                abort_unless($base->workspace_id === $workspace->id && $base->project_id === $project?->id && $this->allowed($actor, $workspace, $project, 'plan.update'), 403);
                $group = $base->plan_group_id;
                if (Plan::where('plan_group_id', $group)->max('version_no') !== $base->version_no) {
                    $this->fail('Hãy tạo phiên bản từ bản mới nhất.');
                }
                $version = $base->version_no + 1;
                $active = Plan::where('plan_group_id', $group)->where('is_active', true)->value('id');
            }
            foreach ($payload['tasks'] as $row) {
                if (! empty($row['task_id'])) {
                    $task = $workspace->tasks()->where('project_id', $project?->id)->findOrFail($row['task_id']);
                    Gate::forUser($actor)->authorize('view', $task);
                }
            }

            return Plan::create(['workspace_id' => $workspace->id, 'project_id' => $project?->id, 'plan_group_id' => $group, 'parent_plan_id' => $base?->id, 'version_no' => $version,
                'created_by' => $actor->id, 'ai_run_id' => $aiRunId, 'name' => $name, 'status' => 'draft', 'data' => ['schema_version' => 1, 'payload' => $payload, 'fingerprint' => $this->fingerprint($actor, $workspace, $project), 'snapshot_actor_id' => $actor->id, 'base_active_id' => $active, 'work_schedule' => app(TimeTrackingService::class)->workHoursValues($actor, $workspace->timezone), 'source' => $aiRunId ? 'ai' : 'manual']]);
        }, 3);
    }

    public function payload(Plan $plan): array
    {
        if (! is_array($plan->data['payload'] ?? null)) {
            $this->fail('Kế hoạch thiếu dữ liệu chuẩn; hãy tạo bản nháp mới từ trình soạn kế hoạch.');
        }

        return $this->validate($plan->data['payload'], $plan->workspace->timezone);
    }

    public function transition(User $actor, Plan $plan, string $action, string $expected, ?string $reason = null): void
    {
        DB::transaction(function () use ($actor, $plan, $action, $expected, $reason) {
            // Same booking lock order as CalendarService (user, then workspace).
            User::lockForUpdate()->findOrFail($actor->id);
            Workspace::lockForUpdate()->findOrFail($plan->workspace_id);
            $plan = Plan::lockForUpdate()->findOrFail($plan->id);
            $permission = match ($action) {
                'propose' => 'plan.update','approve','reject' => 'plan.approve','apply' => 'plan.apply',default => abort(404)
            };
            abort_unless($this->allowed($actor, $plan->workspace, $plan->project, $permission) && app(TaskPolicy::class)->projectWritable($plan->project), 403);
            if ($plan->status->value !== $expected) {
                $this->fail('Trạng thái kế hoạch đã đổi; tải lại.');
            }
            if ($action === 'propose' && $expected === 'draft') {
                $this->payload($plan);
                $plan->update(['status' => 'proposed']);

                return;
            }
            if (in_array($action, ['approve', 'reject']) && $expected === 'proposed') {
                if ($action === 'approve') {
                    $this->payload($plan);
                }
                if ($action === 'reject' && ! $reason) {
                    $this->fail('Cần lý do từ chối.');
                }
                $plan->update(['status' => $action === 'approve' ? 'approved' : 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'change_summary' => $reason]);

                return;
            }
            if ($action !== 'apply' || $expected !== 'approved') {
                $this->fail('Chuyển trạng thái không hợp lệ.');
            }
            if ($plan->applied_at) {
                return;
            } // Idempotent replay; never duplicate tasks/events.
            abort_unless($this->allowed($actor, $plan->workspace, $plan->project, 'plan.approve'), 403);
            $payload = $this->payload($plan);
            if (! is_string($plan->data['snapshot_actor_id'] ?? null) || ! is_string($plan->data['fingerprint'] ?? null)) {
                $this->fail('Kế hoạch thiếu snapshot nguồn; hãy tạo phiên bản mới để rà soát.');
            }
            $snapshotActor = User::find($plan->data['snapshot_actor_id']);
            if (! $snapshotActor) {
                $this->fail('Người lập nguồn không còn hợp lệ; hãy tạo phiên bản mới.');
            }
            if (! $this->allowed($snapshotActor, $plan->workspace, $plan->project) || $this->fingerprint($snapshotActor, $plan->workspace, $plan->project) !== $plan->data['fingerprint']) {
                $this->fail('Dữ liệu nguồn đã thay đổi. Tạo phiên bản mới để rà soát lại.');
            }
            $active = Plan::where('plan_group_id', $plan->plan_group_id)->where('is_active', true)->lockForUpdate()->first();
            if ($active?->id !== ($plan->data['base_active_id'] ?? null)) {
                $this->fail('Phiên bản đang áp dụng đã thay đổi.');
            }
            $order = array_flip(array_column($payload['tasks'], 'ref'));
            $map = [];
            $pending = $payload['tasks'];
            if ($active) {
                foreach ($active->calendarEvents()->with('task')->get() as $event) {
                    if ($event->status->value !== 'planned' || $event->starts_at->lte(now()) || $event->task?->status->value !== 'TODO') {
                        continue;
                    }
                    if ($event->user_id !== $actor->id || $event->timeEntries()->exists()) {
                        $this->fail('Không thể thay lịch của người khác hoặc lịch có giờ thực tế.');
                    }
                    if ($event->status->value !== 'cancelled') {
                        $event->update(['status' => 'cancelled']);
                        app(TaskHistory::class)->record($actor, $event->task, 'plan.calendar_replaced', ['event_id' => $event->id, 'new_plan_id' => $plan->id]);
                    }
                }
            }
            while ($pending) {
                $progress = false;
                foreach ($pending as $key => $row) {
                    if (! empty($row['parent_ref']) && ! isset($map[$row['parent_ref']])) {
                        continue;
                    }
                    $task = empty($row['task_id']) ? null : $plan->workspace->tasks()->where('project_id', $plan->project_id)->findOrFail($row['task_id']);
                    if ($task && ($task->status->value !== 'TODO' || $task->started_at || $task->archived_at)) {
                        $this->fail('Chỉ chỉnh task chưa bắt đầu, chưa lưu trữ.');
                    }
                    $values = array_intersect_key($row, array_flip(['title', 'description', 'task_type', 'priority', 'estimate_minutes', 'assignee_id', 'reviewer_id']));
                    $values['sort_order'] = $order[$row['ref']];
                    $values['parent_task_id'] = empty($row['parent_ref']) ? null : $map[$row['parent_ref']];
                    if ($task) {
                        $values['expected_revision'] = $task->revision;
                    }
                    $task = app(TaskService::class)->save($actor, $plan->workspace, $plan->project, $values, $task);
                    $map[$row['ref']] = $task->id;
                    unset($pending[$key]);
                    $progress = true;
                    TaskActivity::create(['task_id' => $task->id, 'actor_id' => $actor->id, 'type' => 'HISTORY', 'source' => 'user', 'plan_id' => $plan->id, 'event_type' => 'plan.applied', 'data' => ['version' => $plan->version_no]]);
                }if (! $progress) {
                    $this->fail('Không thể dựng cây task.');
                }
            }
            foreach ($payload['dependencies'] as $edge) {
                $task = Task::findOrFail($map[$edge['successor']]);
                $pre = $map[$edge['predecessor']];
                $existing = $task->incomingDependencies()->where('predecessor_task_id', $pre)->first();
                if ($existing) {
                    if ($existing->lag_minutes != (int) $edge['lag_minutes']) {
                        $this->fail('Dependency đã tồn tại với lag khác; sửa trực tiếp trước khi lập phiên bản.');
                    }
                } else {
                    app(TaskWorkflowService::class)->dependency($actor, $task, ['predecessor_task_id' => $pre, 'lag_minutes' => $edge['lag_minutes']]);
                }
            }
            foreach ($payload['slots'] as $slot) {
                $task = Task::findOrFail($map[$slot['task_ref']]);
                $event = app(CalendarService::class)->save($actor, $plan->workspace, ['project_id' => $plan->project_id, 'task_id' => $task->id, 'event_type' => 'TASK_BLOCK', 'title' => $task->title, 'starts_at' => $slot['starts_at'], 'ends_at' => $slot['ends_at'], 'timezone' => $plan->workspace->timezone, 'all_day' => false, 'is_busy' => true, 'visibility' => $plan->project_id ? 'project' : 'private', 'status' => 'planned', 'participants' => []]);
                $event->update(['plan_id' => $plan->id, 'source' => 'plan']);
            }
            if ($active) {
                $active->update(['is_active' => false, 'status' => 'superseded']);
            }
            $data = $plan->data;
            $data['applied_task_ids'] = $map;
            $plan->update(['data' => $data, 'is_active' => true, 'applied_at' => now()]);
        }, 3);
    }

    public function impact(User $actor, Workspace $workspace, ?Project $project): array
    {
        abort_unless($this->allowed($actor, $workspace, $project), 403);
        $tasks = $workspace->tasks()->where('project_id', $project?->id)->whereNull('archived_at')->with('blockers', 'incomingDependencies.predecessor')->get();
        $result = [];
        foreach ($tasks as $task) {
            if (in_array($task->status->value, ['DONE', 'CANCELLED']) || $task->task_type->value === 'GROUP') {
                continue;
            }
            $reasons = [];
            if ($task->deadline_at?->isPast()) {
                $reasons[] = 'Quá hạn';
            }
            if ($task->blockers->where('status', BlockerStatus::OPEN)->isNotEmpty()) {
                $reasons[] = 'Blocker đang mở';
            }
            foreach ($task->incomingDependencies as $edge) {
                if ($edge->predecessor->status->value !== 'DONE' || ! $edge->predecessor->completed_at || $edge->predecessor->completed_at->addMinutes($edge->lag_minutes)->isFuture()) {
                    $reasons[] = 'Chờ FS: '.$edge->predecessor->code;
                }
            }
            if ($reasons) {
                $result[] = ['task' => $task, 'reasons' => $reasons];
            }
        }

        return $result;
    }
}
