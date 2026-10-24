<?php

namespace App\Support;

use App\Enums\CalendarEventType;
use App\Enums\IntegrationProvider;
use App\Enums\PlanStatus;
use App\Enums\TaskType;
use App\Models\AiCall;
use App\Models\CalendarEvent;
use App\Models\GithubLog;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskBlocker;
use App\Models\TaskDependency;
use App\Models\TaskTemplateRun;
use App\Models\TaskWatcher;
use App\Models\TimeEntry;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Structural invariants for Eloquent writes. Authorization is implemented in later stages. */
class ReferenceIntegrity
{
    public function validate(Model $model): void
    {
        $model->unsetRelations();
        $workspaceId = $this->workspaceId($model);
        foreach (['workspace_id', 'project_id'] as $scopeField) {
            if ($model->exists && in_array($scopeField, $model->getFillable(), true) && $model->isDirty($scopeField)) {
                $this->fail($scopeField, 'Không được chuyển phạm vi của dữ liệu đã tạo; cần quy trình chuyển riêng.');
            }
        }
        foreach (config('domain_schema.references.'.$model->getTable(), []) as $field => $table) {
            $id = $model->getAttribute($field);
            if ($id === null) {
                continue;
            }
            $row = DB::table($table)->where('id', $id)->first();
            if (! $row) {
                $this->fail($field, 'Dữ liệu tham chiếu không tồn tại.');
            }
            $relatedWorkspace = $table === 'workspaces' ? $row->id : ($row->workspace_id ?? null);
            if ($relatedWorkspace !== null && $relatedWorkspace !== $workspaceId) {
                $this->fail($field, 'Dữ liệu phải thuộc cùng workspace.');
            }
        }

        foreach (['estimate_minutes', 'lag_minutes', 'sort_order', 'input_tokens', 'output_tokens', 'attempt_count'] as $field) {
            if ($model->getAttribute($field) !== null && $model->getAttribute($field) < 0) {
                $this->fail($field, 'Giá trị không được âm.');
            }
        }
        foreach (['version_no', 'attempt_no'] as $field) {
            if ($model->getAttribute($field) !== null && $model->getAttribute($field) < 1) {
                $this->fail($field, 'Giá trị phải lớn hơn hoặc bằng 1.');
            }
        }
        foreach ([['starts_at', 'ends_at'], ['started_at', 'ended_at'], ['period_start', 'period_end']] as [$start, $end]) {
            if ($model->getAttribute($start) && $model->getAttribute($end) && CarbonImmutable::parse($model->getAttribute($start))->gte(CarbonImmutable::parse($model->getAttribute($end)))) {
                $this->fail($end, 'Thời điểm kết thúc phải sau thời điểm bắt đầu.');
            }
        }
        if ($model->getAttribute('timezone') !== null && ! in_array($model->getAttribute('timezone'), timezone_identifiers_list(), true)) {
            $this->fail('timezone', 'Múi giờ không hợp lệ.');
        }

        if ($model instanceof Role) {
            if (! is_array($model->permissions) || ! array_is_list($model->permissions) || array_filter($model->permissions, fn ($value) => ! is_string($value)) || array_diff($model->permissions, config('permissions.catalog'))) {
                $this->fail('permissions', 'Danh sách quyền không hợp lệ.');
            }
        }
        if ($model instanceof Project) {
            $this->assertAcyclicParent($model, 'parent_project_id');
            if ($model->github_connection_id && $model->githubConnection->provider !== IntegrationProvider::GITHUB) {
                $this->fail('github_connection_id', 'Kết nối phải là GitHub.');
            }
            if ($model->start_date && $model->deadline && $model->deadline->lt($model->start_date)) {
                $this->fail('deadline', 'Deadline không được trước ngày bắt đầu.');
            }
        }
        if ($model instanceof Task) {
            $this->assertAcyclicParent($model, 'parent_task_id');
            if ($model->parent_task_id) {
                $this->sameProject($model, $model->parent);
                if ($model->parent->task_type !== TaskType::GROUP) {
                    $this->fail('parent_task_id', 'Chỉ GROUP được chứa công việc con.');
                }
            }
            if ($model->task_type !== TaskType::WORK && $model->estimate_minutes !== null) {
                $this->fail('estimate_minutes', 'Chỉ WORK lá có ước lượng thời gian.');
            }
            if ($model->exists && $model->task_type !== TaskType::GROUP && $model->children()->exists()) {
                $this->fail('task_type', 'Công việc đang có con phải giữ loại GROUP.');
            }
        }
        if ($model instanceof TaskDependency) {
            if ($model->predecessor_task_id === $model->successor_task_id) {
                $this->fail('successor_task_id', 'Không được phụ thuộc chính mình.');
            }
            $this->sameProject($model->predecessor, $model->successor);
            $this->assertAcyclicDependency($model);
        }
        if ($model instanceof Plan) {
            $groupVersion = Plan::query()->where('plan_group_id', $model->plan_group_id)->when($model->exists, fn ($query) => $query->whereKeyNot($model->id))->first();
            if ($groupVersion && ($groupVersion->workspace_id !== $model->workspace_id || $groupVersion->project_id !== $model->project_id)) {
                $this->fail('plan_group_id', 'Nhóm kế hoạch không được sử dụng ở phạm vi khác.');
            }
            if ($model->exists && ($model->isDirty('plan_group_id') || $model->isDirty('version_no'))) {
                $this->fail('plan_group_id', 'Không được thay đổi định danh phiên bản đã tạo.');
            }
            if (! Str::isUuid($model->plan_group_id)) {
                $this->fail('plan_group_id', 'Mã nhóm kế hoạch phải là UUID.');
            }
            $this->assertAcyclicParent($model, 'parent_plan_id');
            if ($model->parent_plan_id) {
                $this->sameProject($model, $model->parent);
                if ($model->parent->plan_group_id !== $model->plan_group_id || $model->parent->version_no >= $model->version_no) {
                    $this->fail('parent_plan_id', 'Phiên bản nền phải thuộc cùng nhóm và có số phiên bản nhỏ hơn.');
                }
            }
            if ($model->ai_run_id) {
                $this->sameProject($model, $model->aiRun);
            }
            if ($model->is_active && ($model->status !== PlanStatus::APPROVED || ! $model->applied_at)) {
                $this->fail('is_active', 'Chỉ kế hoạch đã duyệt và áp dụng mới được active.');
            }
        }
        if ($model instanceof CalendarEvent) {
            if ($model->event_type === CalendarEventType::TASK_BLOCK && ! $model->task_id) {
                $this->fail('task_id', 'TASK_BLOCK phải liên kết công việc.');
            }
            foreach (['task', 'plan'] as $relation) {
                if ($model->getAttribute($relation.'_id')) {
                    $this->sameProject($model, $model->$relation);
                }
            }
        }
        if ($model instanceof TaskActivity && $model->plan_id) {
            $this->sameProject($model->task, $model->plan);
        }
        if ($model instanceof TimeEntry && $model->calendar_event_id) {
            $event = $model->calendarEvent;
            if ($event->task_id !== $model->task_id || $event->user_id !== $model->user_id) {
                $this->fail('calendar_event_id', 'Phiên làm việc phải khớp task và người sở hữu lịch.');
            }
        }
        if ($model instanceof GithubLog && $model->task_id && $model->task->project_id !== $model->project_id) {
            $this->fail('task_id', 'Task không thuộc project của sự kiện.');
        }
    }

    private function workspaceId(Model $model): ?string
    {
        if ($model instanceof Workspace) {
            return $model->getKey();
        }
        if (in_array('workspace_id', $model->getFillable(), true)) {
            return $model->workspace_id;
        }

        return match (true) {
            $model instanceof ProjectMember, $model instanceof GithubLog => $model->project?->workspace_id,
            $model instanceof TaskDependency => $model->predecessor?->workspace_id,
            $model instanceof TaskActivity, $model instanceof TimeEntry, $model instanceof TaskBlocker => $model->task?->workspace_id,
            $model instanceof TaskWatcher => $model->task?->workspace_id,
            $model instanceof TaskTemplateRun => $model->template?->workspace_id,
            $model instanceof AiCall => $model->aiRun?->workspace_id,
            default => null,
        };
    }

    private function sameProject(Model $a, Model $b): void
    {
        if ($a->project_id !== $b->project_id) {
            $this->fail('project_id', 'Các dữ liệu phải thuộc cùng project hoặc cùng phạm vi cá nhân.');
        }
    }

    private function assertAcyclicParent(Model $model, string $field): void
    {
        $seen = [$model->getKey()];
        $cursor = $model->getAttribute($field);
        while ($cursor !== null) {
            if (in_array($cursor, $seen, true)) {
                $this->fail($field, 'Không được tạo chu trình cha/con.');
            }
            $seen[] = $cursor;
            $cursor = DB::table($model->getTable())->where('id', $cursor)->value($field);
        }
    }

    private function assertAcyclicDependency(TaskDependency $dependency): void
    {
        $queue = [$dependency->successor_task_id];
        $seen = [];
        while ($queue) {
            $id = array_pop($queue);
            if ($id === $dependency->predecessor_task_id) {
                $this->fail('successor_task_id', 'Dependency không được tạo chu trình.');
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $next = TaskDependency::query()->where('predecessor_task_id', $id)->when($dependency->exists, fn ($q) => $q->whereKeyNot($dependency->id))->pluck('successor_task_id')->all();
            array_push($queue, ...$next);
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
