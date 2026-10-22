<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Support\UiLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskEditConflict
{
    public const FIELDS = ['code', 'title', 'description', 'priority', 'estimate_minutes', 'deadline_at', 'assignee_id', 'reviewer_id', 'task_type', 'parent_task_id', 'group_label', 'sort_order'];

    public const LABELS = ['code' => 'Mã', 'title' => 'Tiêu đề', 'description' => 'Mô tả', 'priority' => 'Ưu tiên', 'estimate_minutes' => 'Ước lượng (phút)', 'deadline_at' => 'Hạn chót', 'assignee_id' => 'Người thực hiện', 'reviewer_id' => 'Người duyệt', 'task_type' => 'Loại', 'parent_task_id' => 'Nhóm cha', 'group_label' => 'Nhãn nhóm', 'sort_order' => 'Thứ tự'];

    public function values(Task $task): array
    {
        $v = [];
        foreach (self::FIELDS as $field) {
            $value = $task->getAttribute($field);
            $v[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }
        $v['deadline_at'] = $task->deadline_at?->setTimezone($task->workspace->timezone)->format('Y-m-d\TH:i');

        return $v;
    }

    public function prepare(Request $request, Task $task, array $input, string $surface): array
    {
        $mine = array_intersect_key($input, array_flip(self::FIELDS));
        $current = $this->values($task);
        $rows = [];
        foreach ($mine as $field => $value) {
            if ((string) $value !== (string) ($current[$field] ?? null)) {
                $rows[] = ['field' => $field, 'label' => self::LABELS[$field], 'mine' => $this->human($task, $field, $value), 'server' => $this->human($task, $field, $current[$field] ?? null)];
            }
        }
        $token = (string) Str::uuid();
        $all = $request->session()->get('task_edit_conflicts', []);
        $all = array_filter($all, fn ($p) => ($p['created_at'] ?? 0) >= time() - 1200);
        while (count($all) >= 5) {
            array_shift($all);
        }
        $all[$token] = ['actor_id' => $request->user()->id, 'task_id' => $task->id, 'workspace_id' => $task->workspace_id, 'project_id' => $task->project_id, 'revision' => $task->revision, 'created_at' => time(), 'surface' => $surface, 'mine' => $mine, 'server' => $current, 'fields' => array_column($rows, 'field'), 'deadline_reason' => $input['deadline_reason'] ?? null];
        $request->session()->put('task_edit_conflicts', $all);

        return ['token' => $token, 'revision' => $task->revision, 'submitted_revision' => $input['expected_revision'] ?? null, 'rows' => $rows, 'deadline_reason' => $input['deadline_reason'] ?? null, 'surface' => $surface];
    }

    private function human(Task $task, string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Trống / chưa chọn';
        }
        if (in_array($field, ['assignee_id', 'reviewer_id'], true)) {
            return User::whereHas('workspaceMemberships', fn ($q) => $q->where('workspace_id', $task->workspace_id))->whereKey($value)->value('name') ?? 'Người đã phân công (không còn trong workspace)';
        }
        if ($field === 'parent_task_id') {
            return Task::where('workspace_id', $task->workspace_id)->where('project_id', $task->project_id)->whereKey($value)->value('title') ?? 'Nhóm không còn trong phạm vi';
        }

        return in_array($field, ['priority', 'task_type'], true) ? UiLabels::text($value) : (string) $value;
    }

    public function resolve(Request $request, Task $task, array $input): array
    {
        $p = $request->session()->get('task_edit_conflicts.'.$input['conflict_token']);
        abort_unless(is_array($p) && $p['actor_id'] === $request->user()->id && $p['task_id'] === $task->id && $p['workspace_id'] === $task->workspace_id && $p['project_id'] === $task->project_id, 404);
        if ($p['created_at'] < time() - 1200) {
            throw ValidationException::withMessages(['conflict_token' => 'Bản đối chiếu đã hết hạn 20 phút. Hãy mở lại dữ liệu hiện tại trước khi sửa.']);
        }
        if ((int) $input['expected_revision'] !== (int) $p['revision']) {
            throw ValidationException::withMessages(['expected_revision' => 'Phiên bản của bản đối chiếu không hợp lệ.']);
        }
        $choices = $input['choices'] ?? [];
        $keys = array_keys($choices);
        sort($keys);
        $required = $p['fields'];
        sort($required);
        if ($keys !== $required) {
            throw ValidationException::withMessages(['choices' => 'Cần chọn cho đúng từng trường trong bản đối chiếu.']);
        }
        $data = $p['mine'];
        foreach ($choices as $field => $choice) {
            if ($choice === 'server') {
                $data[$field] = $p['server'][$field];
            }
        }
        $data['expected_revision'] = $p['revision'];
        $data['deadline_reason'] = $input['deadline_reason'] ?? $p['deadline_reason'];

        return ['data' => $data, 'surface' => $p['surface']];
    }

    public function forget(Request $request, string $token): void
    {
        $request->session()->forget('task_edit_conflicts.'.$token);
    }
}
