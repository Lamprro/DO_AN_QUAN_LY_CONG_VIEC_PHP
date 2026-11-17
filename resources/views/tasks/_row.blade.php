@php
    $itemRoutePrefix = $task->project_id ? 'workspaces.projects.tasks' : 'workspaces.tasks';
    $itemRouteParams = $task->project_id ? [$task->workspace_id, $task->project_id] : [$task->workspace_id];
    $isSubtask = ($tier ?? 2) === 3;
    $hasSubtasks = !empty($hasSubtasks);
@endphp
<tr data-task-id="{{ $task->id }}"
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
                        <a data-task-panel data-panel-field="priority" href="{{ route($itemRoutePrefix.'.edit', [...$itemRouteParams, $task]) }}" class="priority-link">Ưu tiên: {{ \App\Support\UiLabels::text($task->priority) }}</a>
                    @else
                        <x-status :value="$task->priority"/>
                    @endcan
                    @if($task->archived_at)<span class="badge">Đã lưu trữ</span>@endif
                </div>
            </div>
        </div>
    </td>
    <td data-label="Thuộc Nhóm / Cha">
        @if($isSubtask)
            @if($task->parent)
                <div>
                    <a href="{{ route($itemRoutePrefix.'.show', [...$itemRouteParams, $task->parent]) }}" style="font-size: 12px; font-weight: 650; color: var(--text); text-decoration: none;" title="{{ $task->parent->title }}">
                        📋 {{ \Illuminate\Support\Str::limit($task->parent->title, 22) }}
                    </a>
                    <div style="margin-top: 2px; display: flex; align-items: center; gap: 4px;">
                        <span class="badge" style="font-size: 10px; font-family: var(--font-mono); font-weight: 700; background: var(--bg-subtle); color: var(--text-muted);">
                            {{ strlen($task->parent->code) > 15 ? \Illuminate\Support\Str::limit($task->parent->code, 10) : $task->parent->code }}
                        </span>
                        @if($task->parent->parent)
                            <span style="font-size: 10px; color: var(--text-muted);">· {{ \Illuminate\Support\Str::limit($task->parent->parent->title, 14) }}</span>
                        @endif
                    </div>
                </div>
            @else
                <span class="muted" style="font-size: 11px;">—</span>
            @endif
        @else
            @if($task->parent)
                <div>
                    <a href="{{ route($itemRoutePrefix.'.show', [...$itemRouteParams, $task->parent]) }}" style="font-size: 12px; font-weight: 700; color: var(--text); text-decoration: none;" title="{{ $task->parent->title }}">
                        📁 {{ \Illuminate\Support\Str::limit($task->parent->title, 22) }}
                    </a>
                    <div style="margin-top: 2px;">
                        <span class="badge" style="font-size: 10px; font-family: var(--font-mono); font-weight: 700; background: rgba(99, 102, 241, 0.1); color: var(--primary);">
                            {{ strlen($task->parent->code) > 15 ? \Illuminate\Support\Str::limit($task->parent->code, 10) : $task->parent->code }}
                        </span>
                    </div>
                </div>
            @elseif($task->task_type->value === 'GROUP')
                <span class="badge" style="font-size: 10px; background: rgba(99, 102, 241, 0.15); color: var(--primary);">📁 Nhóm gốc</span>
            @else
                <span class="muted" style="font-size: 11px;">Độc lập (Gốc)</span>
            @endif
        @endif
    </td>
    <td data-label="Trạng thái">
        @if($task->task_type->value !== 'GROUP' && (auth()->user()->can('execute', $task) || auth()->user()->can('review', $task)))
            <a data-task-panel class="task-status-link" href="{{ route($itemRoutePrefix.'.status.edit', [...$itemRouteParams, $task]) }}" aria-label="Đổi trạng thái {{ $task->title }}"><x-status :value="$task->status"/> ▾</a>
        @else
            <x-status :value="$task->status"/>
        @endif
    </td>
    <td data-label="Người thực hiện">{{ $task->assignee?->name ?? 'Chưa phân công' }}</td>
    <td data-label="Hạn hoàn thành"><span @class(['task-overdue' => $task->deadline_at?->isPast() && !in_array($task->status->value, ['DONE', 'CANCELLED'])])>{{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i') ?? 'Chưa đặt hạn' }}</span></td>
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
