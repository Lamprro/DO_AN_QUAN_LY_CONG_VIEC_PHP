<?php

namespace App\Services;

use App\Exceptions\AiProviderUnavailable;
use App\Models\AiRun;
use App\Models\CalendarEvent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Support\ProjectAccess;
use Illuminate\Validation\ValidationException;

class PlanningContext
{
    public function allowed(User $actor, AiRun $run, string $permission = 'plan.view'): bool
    {
        return ($permission !== 'plan.create' || app(TaskPolicy::class)->projectWritable($run->project)) && ($run->context_snapshot['schema_version'] ?? null) === 1 && app(PlanService::class)->allowed($actor, $run->workspace, $run->project, $permission);
    }

    public function capture(User $actor, Workspace $workspace, ?Project $project, ?Plan $base = null): array
    {
        abort_unless(app(TaskPolicy::class)->projectWritable($project) && app(PlanService::class)->allowed($actor, $workspace, $project, 'plan.create'), 403);
        $tasks = $workspace->tasks()->where('project_id', $project?->id)->whereNull('archived_at')->with('blockers', 'incomingDependencies')->orderBy('id')->limit(201)->get();
        if ($tasks->count() > 200) {
            throw ValidationException::withMessages(['project_id' => 'Phạm vi AI tối đa 200 task; chia nhỏ project.']);
        }
        $reviewers = app(ProjectAccess::class)->eligibleMembers($workspace, $project, 'task.review')->pluck('user_id')->all();
        $members = app(ProjectAccess::class)->eligibleMembers($workspace, $project)->map(fn ($m) => ['user_id' => $m->user_id, 'name' => $m->user->name, 'can_review' => in_array($m->user_id, $reviewers, true)])->all();

        return ['schema_version' => 1, 'fingerprint' => app(PlanService::class)->fingerprint($actor, $workspace, $project), 'base_plan_id' => $base?->id,
            'work_schedule' => config('work_schedule'), 'timezone' => $workspace->timezone,
            'tasks' => $tasks->map(fn ($t) => ['id' => $t->id, 'code' => $t->code, 'title' => $t->title, 'task_type' => $t->task_type->value, 'status' => $t->status->value, 'estimate_minutes' => $t->estimate_minutes, 'assignee_id' => $t->assignee_id, 'parent_task_id' => $t->parent_task_id, 'deadline_at' => $t->deadline_at?->toIso8601String(), 'blockers' => $t->blockers->map(fn ($b) => ['reason' => $b->reason, 'status' => $b->status->value])->all(), 'dependencies' => $t->incomingDependencies->map(fn ($d) => ['predecessor_task_id' => $d->predecessor_task_id, 'lag_minutes' => $d->lag_minutes])->all()])->all(),
            'members' => $members, 'calendar_busy' => CalendarEvent::where('user_id', $actor->id)->where('is_busy', true)->where('status', '!=', 'cancelled')->where('ends_at', '>', now())->orderBy('starts_at')->limit(300)->get()->map(fn ($e) => ['starts_at' => $e->starts_at->toIso8601String(), 'ends_at' => $e->ends_at->toIso8601String()])->all(),
            'plan_schema' => ['schema_version' => 1, 'tasks' => [['ref' => 'work1', 'task_id' => 'optional existing UUID', 'title' => 'string', 'task_type' => 'WORK|GROUP|MILESTONE', 'priority' => 'low|medium|high|urgent', 'estimate_minutes' => 'integer or null (WORK only)', 'parent_ref' => 'optional GROUP ref', 'assignee_id' => 'optional eligible UUID', 'reviewer_id' => 'optional reviewer UUID']], 'dependencies' => [['predecessor' => 'ref', 'successor' => 'ref', 'lag_minutes' => 0]], 'slots' => [['task_ref' => 'ref', 'starts_at' => 'Y-m-dTH:i', 'ends_at' => 'Y-m-dTH:i']]],
            'safety' => 'Only requester-owned slots can be applied. Output creates draft only; user approves and applies explicitly. Never invent completed work.'];
    }

    public function result(AiRun $run, string $text): ?Plan
    {
        if (in_array($run->operation->value, ['report', 'assistance'], true)) {
            return null;
        }
        try {
            $input = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                throw new \JsonException;
            }app(PlanService::class)->validate($input, $run->workspace->timezone);
        } catch (\JsonException|ValidationException) {
            throw new AiProviderUnavailable('Kết quả AI không đúng schema kế hoạch; chưa tạo task/lịch.');
        }
        if (! hash_equals($run->context_snapshot['fingerprint'] ?? '', app(PlanService::class)->fingerprint($run->requester, $run->workspace, $run->project))) {
            throw new AiProviderUnavailable('Dữ liệu đã thay đổi khi AI đang xử lý; cần gửi yêu cầu mới.');
        }
        $base = empty($run->context_snapshot['base_plan_id']) ? null : Plan::findOrFail($run->context_snapshot['base_plan_id']);
        try {
            return app(PlanService::class)->create($run->requester, $run->workspace, $run->project, 'AI '.$run->operation->value.' '.now()->format('Y-m-d H:i'), $input, $base, $run->id);
        } catch (ValidationException) {
            throw new AiProviderUnavailable('Không thể tạo phiên bản từ kết quả AI; kiểm tra phạm vi và phiên bản nền.');
        }
    }
}
