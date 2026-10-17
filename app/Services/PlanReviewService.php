<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\UiLabels;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlanReviewService
{
    public function diff(Plan $plan): array
    {
        $parent = $plan->parent;
        $old = $parent?->data['payload'] ?? ['tasks' => [], 'dependencies' => [], 'slots' => []];
        $new = $plan->data['payload'];
        $map = $parent?->data['applied_task_ids'] ?? [];
        $key = fn ($r) => ! empty($r['task_id']) ? 'id:'.$r['task_id'] : (isset($map[$r['ref']]) ? 'id:'.$map[$r['ref']] : 'ref:'.$r['ref']);
        $a = [];
        $b = [];
        foreach ($old['tasks'] as $r) {
            $a[$key($r)] = $r;
        }foreach ($new['tasks'] as $r) {
            $b[$key($r)] = $r;
        }$rows = [];
        $oldPosition = [];
        foreach ($old['tasks'] as $i => $r) {
            $oldPosition[$key($r)] = $i;
        }
        $newPosition = array_flip(array_keys($b));
        foreach ($b as $id => $row) {
            $before = $a[$id] ?? null;
            $fields = [];
            foreach (['title', 'description', 'task_type', 'parent_ref', 'priority', 'estimate_minutes', 'assignee_id', 'reviewer_id', 'sort_order'] as $field) {
                if (($before[$field] ?? null) !== ($row[$field] ?? null)) {
                    $fields[$field] = ['before' => $before[$field] ?? null, 'after' => $row[$field] ?? null];
                }
            }
            if ($before && $oldPosition[$id] !== $newPosition[$id]) {
                $fields['position'] = ['before' => $oldPosition[$id] + 1, 'after' => $newPosition[$id] + 1];
            }
            $rows[] = ['ref' => $row['ref'], 'title' => $row['title'], 'kind' => ! $before ? 'added' : ($fields ? 'changed' : 'unchanged'), 'fields' => $fields];
        }
        foreach ($a as $id => $row) {
            if (! isset($b[$id])) {
                $rows[] = ['ref' => $row['ref'], 'title' => $row['title'], 'kind' => 'omitted', 'fields' => []];
            }
        }

        return ['rows' => $rows, 'dependencies_before' => $old['dependencies'], 'dependencies_after' => $new['dependencies'], 'slots_before' => $old['slots'], 'slots_after' => $new['slots']];
    }

    public function preview(User $actor, Plan $plan): array
    {
        $service = app(PlanService::class);
        abort_unless($service->allowed($actor, $plan->workspace, $plan->project, 'plan.apply') && $service->allowed($actor, $plan->workspace, $plan->project, 'plan.approve'), 403);
        if ($plan->applied_at || ! in_array($plan->status->value, ['draft', 'proposed', 'approved'], true)) {
            return ['valid' => false, 'errors' => ['Chỉ preview bản chưa áp dụng ở trạng thái nháp/chờ duyệt/đã duyệt.'], 'tasks' => [], 'events' => [], 'replaced' => []];
        }
        // Exact domain apply simulation. All effects are database-only and MUST be rolled back.
        // No provider, filesystem write, external delivery or queued work is invoked by this path.
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            User::lockForUpdate()->findOrFail($actor->id);
            Workspace::lockForUpdate()->findOrFail($plan->workspace_id);
            $sim = Plan::lockForUpdate()->findOrFail($plan->id);
            if ($sim->applied_at || ! in_array($sim->status->value, ['draft', 'proposed', 'approved'], true)) {
                return ['valid' => false, 'errors' => ['Trạng thái đã thay đổi; tải lại kế hoạch.'], 'tasks' => [], 'events' => [], 'replaced' => []];
            }
            $payload = $sim->data['payload'];
            $before = Task::where('workspace_id', $sim->workspace_id)->where('project_id', $sim->project_id)->get()->keyBy('id');
            $oldEvents = CalendarEvent::where('workspace_id', $sim->workspace_id)->where('project_id', $sim->project_id)->get()->keyBy('id');
            if ($sim->status->value !== 'approved') {
                $sim->update(['status' => 'approved']);
            }
            $service->transition($actor, $sim, 'apply', 'approved');
            $sim = $sim->fresh();
            $map = $sim->data['applied_task_ids'];
            $after = Task::where('workspace_id', $sim->workspace_id)->where('project_id', $sim->project_id)->with('assignee', 'reviewer')->get();
            $taskNames = $after->pluck('title', 'id')->all();
            $userNames = [];
            foreach ($after as $t) {
                if ($t->assignee) {
                    $userNames[$t->assignee_id] = $t->assignee->name;
                }if ($t->reviewer) {
                    $userNames[$t->reviewer_id] = $t->reviewer->name;
                }
            }
            foreach ($before as $t) {
                if ($t->assignee) {
                    $userNames[$t->assignee_id] = $t->assignee->name;
                }if ($t->reviewer) {
                    $userNames[$t->reviewer_id] = $t->reviewer->name;
                }
            }
            $human = function ($field, $value) use ($userNames, $taskNames) {
                if (in_array($field, ['assignee_id', 'reviewer_id'], true)) {
                    return $value ? ($userNames[$value] ?? 'Không còn phù hợp') : 'Chưa phân công';
                }
                if ($field === 'parent_task_id') {
                    return $value ? ($taskNames[$value] ?? 'Nhóm không còn phù hợp') : 'Không có';
                }

                return UiLabels::text($value ?? 'Chưa có');
            };
            $active = empty($sim->data['base_active_id']) ? null : Plan::where('workspace_id', $sim->workspace_id)->where('project_id', $sim->project_id)->find($sim->data['base_active_id']);

            $tasks = [];
            foreach ($payload['tasks'] as $row) {
                $task = Task::findOrFail($map[$row['ref']]);
                $old = $before->get($task->id);
                $changes = [];
                foreach (['title', 'description', 'task_type', 'parent_task_id', 'priority', 'estimate_minutes', 'assignee_id', 'reviewer_id', 'sort_order'] as $field) {
                    if (($old?->getRawOriginal($field)) !== $task->getRawOriginal($field)) {
                        $changes[$field] = ['before' => $human($field, $old?->getRawOriginal($field)), 'after' => $human($field, $task->getRawOriginal($field))];
                    }
                }
                $tasks[] = ['ref' => $row['ref'], 'title' => $task->title, 'action' => $old ? 'update' : 'create', 'changes' => $changes];
            }
            $events = $sim->calendarEvents()->get()->map(fn ($e) => ['title' => $e->title, 'starts_at' => $e->starts_at->setTimezone($e->timezone)->format('d/m/Y H:i'), 'ends_at' => $e->ends_at->setTimezone($e->timezone)->format('d/m/Y H:i'), 'timezone' => $e->timezone])->all();
            $replaced = [];
            foreach ($oldEvents as $id => $event) {
                if ($event->status->value !== 'cancelled' && CalendarEvent::find($id)?->status->value === 'cancelled') {
                    $replaced[] = ['title' => $event->title, 'starts_at' => $event->starts_at->format('Y-m-d H:i')];
                }
            }

            return ['valid' => true, 'errors' => [], 'tasks' => $tasks, 'events' => $events, 'replaced' => $replaced, 'base_active_id' => $plan->data['base_active_id'] ?? null, 'base_active_label' => $active ? ($active->name.' · v'.$active->version_no) : null];
        } catch (ValidationException $e) {
            return ['valid' => false, 'errors' => array_merge(...array_values($e->errors())), 'tasks' => [], 'events' => [], 'replaced' => []];
        } catch (HttpException $e) {
            if (! in_array($e->getStatusCode(), [403, 404], true)) {
                throw $e;
            }

            return ['valid' => false, 'errors' => ['Phạm vi hoặc quyền áp dụng không còn hợp lệ.'], 'tasks' => [], 'events' => [], 'replaced' => []];
        } finally {
            DB::rollBack($level);
        }
    }
}
