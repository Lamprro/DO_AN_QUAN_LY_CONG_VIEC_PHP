<x-layout title="Công việc">
<div class="page-heading">
    <div>
        <p class="eyebrow">{{ $project?->name ?? $workspace->name }}</p>
        <h1>Công việc</h1>
        <p class="muted">Tổ chức phân tầng 3 cấp theo chuẩn Jira: Nhóm cha (Epic) › Công việc (Task) › Công việc con (Sub-task).</p>
    </div>
    @if($canCreate)
        <a class="button" data-task-panel href="{{ route($routePrefix.'.create', $routeParams) }}">+ Tạo công việc</a>
    @endif
</div>
@include('tasks._view-nav')
@include('tasks._overview-note')
@include('tasks._filters')
@include('tasks._saved-filters')

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin: 14px 0 10px;">
    <p class="result-summary" style="margin: 0;">
        <label class="bulk-select-all"><input type="checkbox" id="bulk-select-page"> Chọn tất cả</label>
        <span role="status"><strong>{{ $tasks->total() }}</strong> công việc · {{ $tasks->firstItem() ?? 0 }}–{{ $tasks->lastItem() ?? 0 }} trên trang này</span>
    </p>
    <div class="jira-layout-toggle" style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <div style="display: inline-flex; align-items: center; gap: 4px;">
            <button type="button" id="btn-expand-all" class="button secondary compact" style="padding: 3px 8px; font-size: 11px; min-height: 26px;">▼ Mở rộng hết</button>
            <button type="button" id="btn-collapse-all" class="button secondary compact" style="padding: 3px 8px; font-size: 11px; min-height: 26px;">▶ Thu gọn hết</button>
        </div>
        <div style="display: inline-flex; align-items: center; gap: 4px; background: var(--bg-subtle); padding: 3px; border-radius: 8px; border: 1px solid var(--border);">
            <a href="{{ route($routePrefix.'.index', array_merge($routeParams, $filters, ['layout' => 'hierarchy'])) }}" class="button {{ ($displayMode ?? 'hierarchy') === 'hierarchy' ? 'primary' : 'secondary' }}" style="padding: 4px 10px; font-size: 12px; min-height: 28px; text-decoration: none;">
                ⊞ Phân cụm 3 tầng (Jira)
            </a>
            <a href="{{ route($routePrefix.'.index', array_merge($routeParams, $filters, ['layout' => 'flat'])) }}" class="button {{ ($displayMode ?? 'hierarchy') === 'flat' ? 'primary' : 'secondary' }}" style="padding: 4px 10px; font-size: 12px; min-height: 28px; text-decoration: none;">
                ☰ Bảng phẳng
            </a>
        </div>
    </div>
</div>

@include('tasks._bulk-bar')

<section class="card table-wrap mobile-data-table task-list" data-page="tasks-index">
    <table>
        <thead>
            <tr>
                <th>Chọn</th>
                <th>Công việc</th>
                <th>Thuộc Nhóm / Cha</th>
                <th>Trạng thái</th>
                <th>Người thực hiện</th>
                <th>Hạn hoàn thành</th>
                <th style="text-align: right; min-width: 175px;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            @if(($displayMode ?? 'hierarchy') === 'hierarchy')
                @if(($scopeTasks ?? collect())->isEmpty())
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <h2>Chưa có công việc phù hợp</h2>
                                <p>Thử bỏ bớt bộ lọc hoặc tạo công việc đầu tiên.</p>
                                @if($canCreate)
                                    <a class="button" data-task-panel href="{{ route($routePrefix.'.create', $routeParams) }}">+ Tạo công việc</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @else
                    {{-- 1. Hiển thị các cụm Group cha (Epics) --}}
                    @foreach($groups as $group)
                        @php
                            $groupRoutePrefix = $group->project_id ? 'workspaces.projects.tasks' : 'workspaces.tasks';
                            $groupRouteParams = $group->project_id ? [$group->workspace_id, $group->project_id] : [$group->workspace_id];
                            $groupTasks = ($nodes ?? collect())->get($group->id, collect());
                            $p = ($groupsProgress ?? [])[$group->id] ?? ['done' => 0, 'total' => 0, 'percent' => 0];
                        @endphp
                        <tr class="jira-epic-cluster-header" style="background: rgba(99, 102, 241, 0.08); border-top: 2px solid var(--primary); border-bottom: 2px solid rgba(99, 102, 241, 0.2);">
                            <td data-label="Chọn" style="padding: 12px 16px;">
                                @if(auth()->user()->can('update', $group) || auth()->user()->can('archive', $group))
                                    <input type="checkbox" form="bulk-tasks" name="items[{{ $group->id }}]" value="{{ $group->revision }}" aria-label="Chọn {{ $group->title }}">
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td colspan="6" style="padding: 12px 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <button type="button" class="btn-toggle-group" data-toggle-group="{{ $group->id }}" aria-expanded="true" data-collapsed="false" title="Mở rộng / Thu gọn nhóm">▼</button>
                                        <span style="font-size: 1.25rem;">📁</span>
                                        <a href="{{ route($groupRoutePrefix.'.show', [...$groupRouteParams, $group]) }}" style="font-size: 1.05rem; font-weight: 750; color: var(--text); text-decoration: none;">
                                            <span class="jira-issue-code" style="background: var(--primary); color: #fff;">{{ $group->code }}</span>
                                            {{ $group->title }}
                                        </a>
                                        <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: var(--primary); font-weight: 700;">NHÓM CHA (EPIC)</span>
                                        <x-status :value="$group->status" />
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                        <div style="font-size: 0.8125rem; color: var(--text-muted); display: inline-flex; align-items: center; gap: 8px;">
                                            <span>Tiến độ: <strong>{{ $p['done'] }}/{{ $p['total'] }}</strong> việc ({{ $p['percent'] }}%)</span>
                                            <div style="width: 80px; height: 6px; background: var(--border); border-radius: 99px; overflow: hidden; display: inline-block;">
                                                <div style="height: 100%; width: {{ $p['percent'] }}%; background: #10b981;"></div>
                                            </div>
                                        </div>
                                        @can('addChild', $group)
                                            <a class="button secondary compact" data-task-panel href="{{ route($routePrefix.'.create', [...$routeParams, 'parent_task_id' => $group->id]) }}" style="font-size: 12px; padding: 4px 10px; min-height: 28px;">
                                                + Thêm Task vào nhóm
                                            </a>
                                        @endcan
                                        @can('update', $group)
                                            <a class="button secondary compact" data-task-panel href="{{ route($groupRoutePrefix.'.edit', [...$groupRouteParams, $group]) }}" style="font-size: 12px; padding: 4px 8px; min-height: 28px;">Sửa</a>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @if($groupTasks->isEmpty())
                            <tr data-group-owner="{{ $group->id }}" style="background: var(--surface);">
                                <td></td>
                                <td colspan="6" style="padding: 12px 32px; color: var(--text-muted); font-size: 13px; font-style: italic;">Chưa có task nào trong nhóm này. Nhấn "+ Thêm Task vào nhóm" để tạo.</td>
                            </tr>
                        @else
                            @foreach($groupTasks as $childTask)
                                @php
                                    $subtasks = ($nodes ?? collect())->get($childTask->id, collect());
                                @endphp
                                @include('tasks._row', [
                                    'task' => $childTask,
                                    'tier' => 2,
                                    'parentTitle' => $group->title,
                                    'groupOwnerId' => $group->id,
                                    'hasSubtasks' => $subtasks->isNotEmpty(),
                                ])
                                @foreach($subtasks as $subtask)
                                    @include('tasks._row', [
                                        'task' => $subtask,
                                        'tier' => 3,
                                        'parentTitle' => $childTask->title,
                                        'groupOwnerId' => $group->id,
                                        'subtaskOwnerId' => $childTask->id,
                                    ])
                                @endforeach
                            @endforeach
                        @endif
                    @endforeach

                    {{-- 2. Hiển thị các công việc độc lập (không thuộc group nào) --}}
                    @php
                        $standaloneTasks = ($nodes ?? collect())->get('root', collect())->where('task_type', '!=', \App\Enums\TaskType::GROUP);
                    @endphp
                    @if($standaloneTasks->isNotEmpty())
                        @if($groups->isNotEmpty())
                            <tr class="jira-epic-cluster-header" style="background: rgba(100, 116, 139, 0.08); border-top: 2px solid var(--border); border-bottom: 2px solid var(--border);">
                                <td></td>
                                <td colspan="6" style="padding: 10px 16px; font-weight: 700; color: var(--text);">
                                    📋 Công việc độc lập / Chưa phân nhóm ({{ $standaloneTasks->count() }})
                                </td>
                            </tr>
                        @endif
                        @foreach($standaloneTasks as $standaloneTask)
                            @php
                                $subtasks = ($nodes ?? collect())->get($standaloneTask->id, collect());
                            @endphp
                            @include('tasks._row', [
                                'task' => $standaloneTask,
                                'tier' => 2,
                                'hasSubtasks' => $subtasks->isNotEmpty(),
                            ])
                            @foreach($subtasks as $subtask)
                                @include('tasks._row', [
                                    'task' => $subtask,
                                    'tier' => 3,
                                    'parentTitle' => $standaloneTask->title,
                                    'subtaskOwnerId' => $standaloneTask->id,
                                ])
                            @endforeach
                        @endforeach
                    @endif
                @endif
            @else
                {{-- Bảng phẳng --}}
                @forelse($tasks as $task)
                    @include('tasks._row', ['task' => $task, 'tier' => $task->parent ? ($task->parent->parent ? 3 : 2) : 2])
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <h2>Chưa có công việc phù hợp</h2>
                                <p>Thử bỏ bớt bộ lọc hoặc tạo công việc đầu tiên.</p>
                                @if($canCreate)
                                    <a class="button" data-task-panel href="{{ route($routePrefix.'.create', $routeParams) }}">+ Tạo công việc</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            @endif
        </tbody>
    </table>
</section>

<div class="task-pagination">{{ $tasks->links() }}</div>
</x-layout>
