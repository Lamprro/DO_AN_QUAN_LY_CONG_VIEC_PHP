@php
    $itemRoutePrefix = $task->project_id ? 'workspaces.projects.tasks' : 'workspaces.tasks';
    $itemRouteParams = $task->project_id ? [$task->workspace_id, $task->project_id] : [$task->workspace_id];
    $isSubtask = ($tier ?? 2) === 3;
    $hasSubtasks = !empty($hasSubtasks);
@endphp
<tr data-task-id="{{ $task->id }}"
    data-code="{{ $task->code }}"
    data-title="{{ $task->title }}"
    data-revision="{{ $task->revision }}"
    data-status="{{ $task->status->value }}"
    data-priority="{{ $task->priority->value }}"
    data-assignee-id="{{ $task->assignee_id ?? '' }}"
    data-deadline="{{ $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') : '' }}"
    data-status-url="{{ route($itemRoutePrefix.'.status.update', [...$itemRouteParams, $task]) }}"
    data-update-url="{{ route($itemRoutePrefix.'.update', [...$itemRouteParams, $task]) }}"
    data-can-update="{{ auth()->user()->can('update', $task) ? '1' : '0' }}"
    data-can-status="{{ ($task->task_type->value !== 'GROUP' && (auth()->user()->can('execute', $task) || auth()->user()->can('review', $task))) ? '1' : '0' }}"
    data-can-assign="{{ (auth()->user()->can('update', $task) && ($canAssign ?? true)) ? '1' : '0' }}"
    @if(!empty($groupOwnerId)) data-group-owner="{{ $groupOwnerId }}" @endif
    @if(!empty($subtaskOwnerId)) data-subtask-owner="{{ $subtaskOwnerId }}" @endif
    @class(['jira-subtask-row' => $isSubtask])
    @if($isSubtask) style="background: var(--bg-subtle);" @endif>
    <td data-label="Chọn">
        @if(auth()->user()->can('update', $task) || auth()->user()->can('archive', $task))
            <input type="checkbox" form="bulk-tasks" name="items[{{ $task->id }}]" value="{{ $task->revision }}" aria-label="Chọn {{ $task->title }}">
        @else
            <span class="muted">—</span>
        @endif
    </td>
    <td data-label="Công việc">
        <div style="display: flex; align-items: flex-start; gap: 8px; @if($isSubtask) padding-left: 28px; @endif">
            @if($hasSubtasks)
                <button type="button" class="btn-toggle-subtasks" data-toggle-task="{{ $task->id }}" aria-expanded="true" data-collapsed="false" title="Mở rộng / Thu gọn công việc con">▼</button>
            @endif
            <span style="font-size: 1rem; line-height: 1.4; color: var(--text-muted); flex-shrink: 0;" aria-hidden="true">{{ $isSubtask ? '↳' : '📋' }}</span>
            <div style="min-width: 0; flex: 1;">
                @include('tasks._alerts', ['task' => $task])
                <div>
                    <a class="task-title-link" data-task-panel href="{{ route($itemRoutePrefix.'.show', [...$itemRouteParams, $task]) }}" style="font-weight: {{ $isSubtask ? '600' : '700' }};">
                        {{ $task->title }}
                    </a>
                </div>
                <div class="task-meta-line" style="margin-top: 4px;">
                    <span class="jira-issue-code" style="font-size: 11px;">{{ strlen($task->code) > 15 ? \Illuminate\Support\Str::limit($task->code, 12) : $task->code }}</span>
                    <span class="badge" style="font-size: 10px;">{{ $isSubtask ? 'SUB-TASK (CẤP 3)' : 'TASK (CẤP 2)' }}</span>
                    @include('tasks._source', ['sourceTask' => $task])
                    @can('update', $task)
                        <button type="button" class="inline-edit-trigger inline-priority-trigger" data-inline-field="priority" title="Nhấp để đổi nhanh độ ưu tiên">
                            <span class="inline-priority-indicator priority-{{ strtolower($task->priority->value) }}">●</span>
                            <span>Ưu tiên: <strong class="inline-priority-text">{{ \App\Support\UiLabels::text($task->priority) }}</strong></span>
                            <span class="inline-caret" aria-hidden="true">▾</span>
                        </button>
                    @else
                        <x-status :value="$task->priority"/>
                    @endcan
                    @if($task->archived_at)<span class="badge">Đã lưu trữ</span>@endif
                </div>
            </div>
        </div>
    </td>
    <td data-label="Thuộc Nhóm / Cha" class="task-parent-cell">
        @if($isSubtask)
            @if($task->parent)
                <div class="parent-lozenge-wrap" title="Thuộc công việc cha: {{ $task->parent->title }} ({{ $task->parent->code }})">
                    <a href="{{ route($itemRoutePrefix.'.show', [...$itemRouteParams, $task->parent]) }}" class="parent-lozenge parent-task-lozenge">
                        <span class="parent-icon">📋</span>
                        <span class="parent-code">{{ strlen($task->parent->code) > 12 ? \Illuminate\Support\Str::limit($task->parent->code, 10) : $task->parent->code }}</span>
                        <span class="parent-title">{{ \Illuminate\Support\Str::limit($task->parent->title, 16) }}</span>
                    </a>
                </div>
            @else
                <span class="muted" style="font-size: 11px;">—</span>
            @endif
        @else
            @if($task->parent)
                <div class="parent-lozenge-wrap" title="Thuộc nhóm: {{ $task->parent->title }} ({{ $task->parent->code }})">
                    <a href="{{ route($itemRoutePrefix.'.show', [...$itemRouteParams, $task->parent]) }}" class="parent-lozenge parent-group-lozenge">
                        <span class="parent-icon">📁</span>
                        <span class="parent-code">{{ strlen($task->parent->code) > 12 ? \Illuminate\Support\Str::limit($task->parent->code, 10) : $task->parent->code }}</span>
                        <span class="parent-title">{{ \Illuminate\Support\Str::limit($task->parent->title, 16) }}</span>
                    </a>
                </div>
            @elseif($task->task_type->value === 'GROUP')
                <span class="badge" style="font-size: 10.5px; background: rgba(99, 102, 241, 0.12); color: var(--primary); white-space: nowrap;">📁 Nhóm gốc</span>
            @else
                <span class="parent-independent-badge">Độc lập (Gốc)</span>
            @endif
        @endif
    </td>
    <td data-label="Trạng thái" class="task-status-cell">
        @if($task->task_type->value !== 'GROUP' && (auth()->user()->can('execute', $task) || auth()->user()->can('review', $task)))
            <button type="button" class="inline-edit-trigger inline-status-trigger" data-inline-field="status" aria-label="Đổi nhanh trạng thái: {{ $task->title }}" title="Nhấp để đổi nhanh trạng thái">
                <x-status :value="$task->status"/>
                <span class="inline-caret" aria-hidden="true">▾</span>
            </button>
        @else
            <x-status :value="$task->status"/>
        @endif
    </td>
    <td data-label="Người thực hiện" class="task-assignee-cell">
        @if(auth()->user()->can('update', $task) && ($canAssign ?? true))
            <button type="button" class="inline-edit-trigger inline-assignee-trigger" data-inline-field="assignee" title="Nhấp để phân công người thực hiện">
                <span class="inline-assignee-avatar {{ !$task->assignee ? 'is-empty' : '' }}" aria-hidden="true">
                    @if($task->assignee)
                        <span class="assignee-initials">{{ mb_strtoupper(mb_substr($task->assignee->name, 0, 1)) }}</span>
                    @else
                        <span class="assignee-empty-icon">👤</span>
                    @endif
                </span>
                <span class="inline-assignee-name">{{ $task->assignee?->name ?? 'Chưa phân công' }}</span>
                <span class="inline-caret" aria-hidden="true">▾</span>
            </button>
        @else
            <span class="inline-assignee-static">
                {{ $task->assignee?->name ?? 'Chưa phân công' }}
            </span>
        @endif
    </td>
    @php($isOverdue = $task->deadline_at?->isPast() && !in_array($task->status->value, ['DONE', 'CANCELLED']))
    <td data-label="Hạn hoàn thành" class="task-deadline-cell">
        @if(auth()->user()->can('update', $task))
            <button type="button" class="inline-edit-trigger inline-deadline-trigger" data-inline-field="deadline" title="Nhấp để đổi hạn hoàn thành">
                <span class="inline-deadline-icon" aria-hidden="true">📅</span>
                <span @class(['inline-deadline-text', 'task-overdue' => $isOverdue])>
                    {{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i') ?? 'Chưa đặt hạn' }}
                </span>
                <span class="inline-caret" aria-hidden="true">▾</span>
            </button>
        @else
            <span @class(['inline-deadline-text', 'task-overdue' => $isOverdue])>
                {{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i') ?? 'Chưa đặt hạn' }}
            </span>
        @endif
    </td>
    <td data-label="Thao tác" class="task-row-actions-cell">
        <div class="task-row-actions">
            @if(!$isSubtask)
                @can('addChild', $task)
                    <a class="button secondary compact task-action-btn action-add-subtask" data-task-panel href="{{ route($itemRoutePrefix.'.create', [...$itemRouteParams, 'parent_task_id' => $task->id]) }}" title="Thêm công việc con cho task này">+ Sub-task</a>
                @endcan
            @endif
            @can('update', $task)
                <a class="button secondary compact task-action-btn" data-task-panel href="{{ route($itemRoutePrefix.'.edit', [...$itemRouteParams, $task]) }}" aria-label="Sửa {{ $task->title }}">Sửa</a>
            @endcan
            <a class="button secondary compact task-action-btn" data-task-panel href="{{ route($itemRoutePrefix.'.show', [...$itemRouteParams, $task]) }}" aria-label="Xem {{ $task->title }}">Xem</a>
        </div>
    </td>
</tr>
