<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateRun;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskTemplateService
{
    public function allowed(User $actor, Workspace $workspace, ?Project $project, bool $write = false): bool
    {
        $policy = app(TaskPolicy::class);

        return $policy->scope($actor, $workspace, $project, 'task.view') && (! $write || $policy->projectWritable($project) && $policy->scope($actor, $workspace, $project, 'task.create') && $policy->scope($actor, $workspace, $project, 'task.assign'));
    }

    public function settings(array $data): array
    {
        $v = Validator::make($data, ['name' => 'required|string|max:160', 'frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])], 'interval' => 'required|integer|between:1,12', 'timezone' => 'required|timezone', 'starts_on' => 'nullable|required_with:frequency|date_format:Y-m-d', 'ends_on' => 'nullable|date_format:Y-m-d|after_or_equal:starts_on', 'local_time' => 'nullable|required_with:frequency|date_format:H:i', 'deadline_offset_days' => 'nullable|integer|between:0,365'])->validate();
        if (! empty($v['frequency'])) {
            $start = app(CalendarService::class)->localTime($v['starts_on'].'T'.$v['local_time'], $v['timezone'], false);
            if ($start->lt(now()->subYear()) || $start->gt(now()->addYears(2))) {
                throw ValidationException::withMessages(['starts_on' => 'Ngày bắt đầu nằm trong khoảng một năm trước đến hai năm tới.']);
            }
            $v['next_run_at'] = $start;
            $v['status'] = 'active';
        } else {
            $v['frequency'] = null;
            $v['next_run_at'] = null;
            $v['status'] = 'paused';
        }

        return $v;
    }

    public function create(User $actor, Workspace $workspace, ?Project $project, array $data): TaskTemplate
    {
        $settings = $this->settings($data);
        $ids = Validator::make($data, ['task_ids' => 'required|array|min:1|max:50', 'task_ids.*' => 'required|uuid|distinct'])->validate()['task_ids'];

        return DB::transaction(function () use ($actor, $workspace, $project, $settings, $ids) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            abort_unless($this->allowed($actor, $workspace, $project, true), 403);
            $tasks = $workspace->tasks()->where('project_id', $project?->id)->whereIn('id', $ids)->whereNull('archived_at')->lockForUpdate()->get();
            if ($tasks->count() !== count($ids)) {
                throw ValidationException::withMessages(['task_ids' => 'Chọn task còn hoạt động trong cùng phạm vi.']);
            }
            $definition = ['schema_version' => 1, 'tasks' => [], 'dependencies' => []];
            foreach ($tasks as $t) {
                if ($t->parent_task_id && ! in_array($t->parent_task_id, $ids, true)) {
                    throw ValidationException::withMessages(['task_ids' => 'Chọn cả GROUP cha để giữ cấu trúc cây.']);
                }
                $definition['tasks'][] = ['ref' => $t->id, 'parent_ref' => $t->parent_task_id, 'title' => $t->title, 'description' => $t->description, 'task_type' => $t->task_type->value, 'priority' => $t->priority->value, 'estimate_minutes' => $t->estimate_minutes, 'assignee_id' => $t->assignee_id, 'reviewer_id' => $t->reviewer_id, 'sort_order' => $t->sort_order, 'group_label' => $t->group_label];
            }
            foreach (TaskDependency::whereIn('successor_task_id', $ids)->get() as $edge) {
                if (! in_array($edge->predecessor_task_id, $ids, true)) {
                    throw ValidationException::withMessages(['task_ids' => 'Chọn cả task tiền nhiệm; không bỏ dependency ngoài tập chọn.']);
                }
                $definition['dependencies'][] = ['predecessor' => $edge->predecessor_task_id, 'successor' => $edge->successor_task_id, 'lag_minutes' => $edge->lag_minutes];
            }

            return TaskTemplate::create($settings + ['workspace_id' => $workspace->id, 'project_id' => $project?->id, 'created_by' => $actor->id, 'definition' => $definition, 'revision' => 1]);
        }, 3);
    }

    public function update(User $actor, TaskTemplate $template, array $data): void
    {
        $settings = $this->settings($data);
        $revision = Validator::make($data, ['revision' => 'required|integer|min:1'])->validate()['revision'];
        DB::transaction(function () use ($actor, $template, $settings, $revision) {
            Workspace::lockForUpdate()->findOrFail($template->workspace_id);
            $t = TaskTemplate::lockForUpdate()->findOrFail($template->id);
            abort_unless($this->allowed($actor, $t->workspace, $t->project, true), 403);
            if ($t->status === 'archived' || $t->revision !== (int) $revision) {
                throw ValidationException::withMessages(['revision' => 'Mẫu đã thay đổi hoặc lưu trữ. Tải lại trước khi lưu.']);
            }
            $scheduleChanged = collect(['frequency', 'interval', 'starts_on', 'ends_on', 'local_time', 'timezone'])->contains(fn ($key) => (string) ($settings[$key] ?? '') !== (string) ($t->{$key} instanceof \DateTimeInterface ? $t->{$key}->format('Y-m-d') : $t->{$key}));
            if (! $scheduleChanged) {
                unset($settings['next_run_at'],$settings['status']);
            }
            // A changed schedule starts strictly after now, never replays previous periods implicitly.
            if ($scheduleChanged && ! empty($settings['frequency'])) {
                $probe = new TaskTemplate(array_replace($t->getAttributes(), $settings));
                $settings['next_run_at'] = $this->advance($probe, CarbonImmutable::now());
            }
            $t->fill($settings + ['revision' => $t->revision + 1, 'last_error' => null]);
            if (! $t->next_run_at) {
                $t->status = 'paused';
            }$t->save();
        }, 3);
    }

    public function state(User $actor, TaskTemplate $template, string $action, int $revision): void
    {
        DB::transaction(function () use ($actor, $template, $action, $revision) {
            Workspace::lockForUpdate()->findOrFail($template->workspace_id);
            $t = TaskTemplate::lockForUpdate()->findOrFail($template->id);
            abort_unless($this->allowed($actor, $t->workspace, $t->project, true), 403);
            if ($t->revision !== $revision || $t->status === 'archived') {
                throw ValidationException::withMessages(['revision' => 'Mẫu đã thay đổi hoặc lưu trữ.']);
            }
            $t->status = match ($action) {
                'archive' => 'archived','resume' => 'active',default => 'paused'
            };
            if ($action === 'resume') {
                if (! $t->frequency) {
                    throw ValidationException::withMessages(['frequency' => 'Mẫu thủ công chưa có lịch lặp.']);
                }
                $t->next_run_at = $this->advance($t, CarbonImmutable::now());
                if (! $t->next_run_at) {
                    throw ValidationException::withMessages(['ends_on' => 'Lịch lặp đã hết hạn.']);
                }
            } else {
                $t->next_run_at = null;
            }
            $t->revision++;
            $t->save();
        }, 3);
    }

    public function advance(TaskTemplate $t, CarbonImmutable $after): ?CarbonImmutable
    {
        if (! $t->frequency || ! $t->starts_on) {
            return null;
        }
        $date = CarbonImmutable::parse($t->starts_on->format('Y-m-d'), $t->timezone);
        $anchor = $date->day;
        for ($n = 0; $n < 4000; $n++) {
            if ($t->ends_on && $date->format('Y-m-d') > $t->ends_on->format('Y-m-d')) {
                return null;
            }
            try {
                $at = app(CalendarService::class)->localTime($date->format('Y-m-d').'T'.$t->local_time, $t->timezone, false);
            } catch (ValidationException) {
                $at = null;
            }
            if ($at && $at->gt($after)) {
                return $at;
            }
            $date = match ($t->frequency) {
                'daily' => $date->addDays($t->interval),'weekly' => $date->addWeeks($t->interval),default => $date->startOfMonth()->addMonths($t->interval)->day(min($anchor, $date->startOfMonth()->addMonths($t->interval)->daysInMonth))
            };
        }
        throw ValidationException::withMessages(['frequency' => 'Không xác định được kỳ tiếp theo.']);
    }

    public function instantiate(User $actor, TaskTemplate $template, string $key, ?int $revision = null, bool $scheduled = false): ?TaskTemplateRun
    {
        return Cache::lock('task-template:generate:'.$template->id, 180)->block(10, fn () => $this->instantiateLocked($actor, $template, $key, $revision, $scheduled));
    }

    private function instantiateLocked(User $actor, TaskTemplate $template, string $key, ?int $revision = null, bool $scheduled = false): ?TaskTemplateRun
    {
        return DB::transaction(function () use ($actor, $template, $key, $revision, $scheduled) {
            Workspace::lockForUpdate()->findOrFail($template->workspace_id);
            $t = TaskTemplate::lockForUpdate()->findOrFail($template->id);
            abort_unless($this->allowed($actor, $t->workspace, $t->project, true), 403);
            $existing = TaskTemplateRun::where('task_template_id', $t->id)->where('occurrence_key', $key)->first();
            if ($existing) {
                return $scheduled ? null : $existing;
            }
            if ($scheduled && ($t->status !== 'active' || ! $t->next_run_at || $t->next_run_at->isFuture() || $key !== 'schedule:'.$t->next_run_at->utc()->format('Y-m-d\TH:i:s\Z'))) {
                return null;
            }
            if ($t->status === 'archived' || (! $scheduled && $t->revision !== $revision)) {
                throw ValidationException::withMessages(['revision' => 'Mẫu đã thay đổi hoặc bị lưu trữ.']);
            }
            $definition = $t->definition;
            $pending = array_column($definition['tasks'], null, 'ref');
            $map = [];
            while ($pending) {
                $progress = false;
                foreach ($pending as $ref => $row) {
                    if ($row['parent_ref'] && ! isset($map[$row['parent_ref']])) {
                        continue;
                    }
                    $values = $row;
                    unset($values['ref'],$values['parent_ref']);
                    $values['parent_task_id'] = $row['parent_ref'] ? $map[$row['parent_ref']] : null;
                    if ($t->deadline_offset_days !== null && $row['task_type'] !== 'GROUP') {
                        $values['deadline_at'] = now($t->workspace->timezone)->addDays($t->deadline_offset_days)->format('Y-m-d\TH:i');
                    }
                    $task = app(TaskService::class)->save($actor, $t->workspace, $t->project, $values);
                    $map[$ref] = $task->id;
                    unset($pending[$ref]);
                    $progress = true;
                }
                if (! $progress) {
                    throw ValidationException::withMessages(['template' => 'Cây mẫu không hợp lệ.']);
                }
            }
            foreach ($definition['dependencies'] as $edge) {
                app(TaskWorkflowService::class)->dependency($actor, Task::findOrFail($map[$edge['successor']]), ['predecessor_task_id' => $map[$edge['predecessor']], 'lag_minutes' => $edge['lag_minutes']]);
            }
            $run = TaskTemplateRun::create(['task_template_id' => $t->id, 'occurrence_key' => $key, 'status' => 'succeeded', 'task_map' => $map]);
            if ($scheduled) {
                $t->next_run_at = $this->advance($t, CarbonImmutable::now());
                if (! $t->next_run_at) {
                    $t->status = 'paused';
                } $t->last_error = null;
                $t->save();
            }

            return $run;
        }, 3);
    }

    public function due(): array
    {
        if (! Schema::hasTable('task_templates')) {
            return ['succeeded' => 0, 'failed' => 0, 'migration_required' => true];
        }
        $result = Cache::lock('task-template:due', 300)->get(fn () => $this->dueLocked());

        return $result === false ? ['succeeded' => 0, 'failed' => 0, 'already_running' => true] : $result;
    }

    private function dueLocked(): array
    {
        if (! Schema::hasTable('task_templates')) {
            return ['succeeded' => 0, 'failed' => 0, 'migration_required' => true];
        }
        $counts = ['succeeded' => 0, 'failed' => 0];
        foreach (TaskTemplate::where('status', 'active')->where('next_run_at', '<=', now())->orderBy('next_run_at')->limit(20)->get() as $t) {
            $key = 'schedule:'.$t->next_run_at->utc()->format('Y-m-d\TH:i:s\Z');
            try {
                $run = $this->instantiate($t->creator, $t, $key, scheduled: true);
                if ($run && $run->status === 'succeeded') {
                    $counts['succeeded']++;
                }
            } catch (\Throwable) {
                $marked = DB::transaction(function () use ($t, $key) {
                    Workspace::lockForUpdate()->findOrFail($t->workspace_id);
                    $current = TaskTemplate::lockForUpdate()->findOrFail($t->id);
                    if ($current->status !== 'active' || ! $current->next_run_at || $key !== 'schedule:'.$current->next_run_at->utc()->format('Y-m-d\TH:i:s\Z')) {
                        return false;
                    }
                    $error = 'Không tạo được kỳ này. Kiểm tra quyền người tạo, người được giao/reviewer, trạng thái project và cấu trúc mẫu; không có task nào được ghi một phần.';
                    TaskTemplateRun::firstOrCreate(['task_template_id' => $t->id, 'occurrence_key' => $key], ['status' => 'failed', 'error' => $error]);
                    $current->last_error = $error;
                    $current->next_run_at = $this->advance($current, CarbonImmutable::now());
                    if (! $current->next_run_at) {
                        $current->status = 'paused';
                    }$current->save();

                    return true;
                }, 3);
                if ($marked) {
                    $counts['failed']++;
                }
            }
        }

        return $counts;
    }
}
