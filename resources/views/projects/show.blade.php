<x-layout :title="$project->name">
<div style="margin-bottom: 1rem;">
    <a href="{{ route('workspaces.projects.index',$workspace) }}" style="font-size: 0.875rem; font-weight: 600;">
        ‹ Danh sách Projects
    </a>
</div>

<section class="card" data-page="project-show" data-project-id="{{ $project->id }}" style="border-top: 4px solid var(--primary);">
    <div class="page-heading">
        <div>
            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                <x-status :value="$project->status" />
                @if($project->archived_at)
                    <span class="badge" style="background: #fff1f2; color: #be123c;">Đã lưu trữ</span>
                @endif
                @if($parent)
                    <span class="badge" style="background: var(--bg-subtle);">
                        Project cha: <a href="{{ route('workspaces.projects.show',[$workspace,$parent]) }}" style="font-weight: 600; margin-left: 0.25rem;">{{ $parent->name }}</a>
                    </span>
                @endif
            </div>
            <h1 style="margin-bottom: 0.5rem;">{{ $project->name }}</h1>
        </div>
    </div>

    @if($project->description)
        <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); margin: 1rem 0; border: 1px solid var(--border-light);">
            <p style="white-space: pre-wrap; margin: 0; color: var(--text);">{{ $project->description }}</p>
        </div>
    @endif

    <div class="kpi-grid" style="margin: 1.25rem 0;">
        <article class="kpi">
            <span>📋 Công việc</span>
            <strong>{{ $project->tasks_count ?? 'N/A' }}</strong>
        </article>
        <article class="kpi">
            <span>👥 Thành viên</span>
            <strong>{{ $project->members_count }}</strong>
        </article>
        <article class="kpi">
            <span>📅 Bắt đầu</span>
            <div style="font-size: 1.25rem; font-weight: 700; margin-top: 0.35rem; color: var(--text);">
                {{ $project->start_date?->format('d/m/Y') ?? 'Chưa có' }}
            </div>
        </article>
        <article class="kpi">
            <span>⏰ Hạn chót</span>
            <div style="font-size: 1.25rem; font-weight: 700; margin-top: 0.35rem; color: {{ $project->deadline && $project->deadline->isPast() ? 'var(--danger)' : 'var(--text)' }};">
                {{ $project->deadline?->format('d/m/Y') ?? 'Chưa có' }}
            </div>
        </article>
    </div>

    <p class="muted" style="font-size: 0.825rem; margin-bottom: 1rem;">
        Người tạo dự án: <strong>{{ $project->creator->name }}</strong>
    </p>

    <div class="notice warning" style="margin: 1rem 0;">
        <span>ℹ️</span>
        <div>Quyền project con độc lập với project cha. Khi project bị lưu trữ hoặc đã Hoàn thành/Đã hủy, các thao tác ghi task/lịch/kế hoạch sẽ được tự động bảo vệ để bảo toàn dữ liệu.</div>
    </div>

    <div style="padding-top: 1rem; border-top: 1px solid var(--border);">
        <h3 style="font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.75rem;">Hành động Dự án</h3>
        <div class="actions">
            @if($canViewTasks)
                <a class="button" href="{{ route('workspaces.projects.tasks.index',[$workspace,$project,'view'=>'board']) }}" style="background: #0c66e4;">
                    📊 Bảng Kanban (Jira Board)
                </a>
                <a class="button secondary" href="{{ route('workspaces.projects.tasks.index',[$workspace,$project]) }}">
                    📋 Xem Danh sách Công việc
                </a>
            @endif
            @if($canViewCalendar)
                <a class="button secondary" href="{{ route('workspaces.projects.timeline',[$workspace,$project]) }}">
                    📊 Timeline Tiến độ
                </a>
            @endif
            @if($canViewPlans)
                <a class="button secondary" href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>($project ?? null)?->id]) }}">
                    📑 Kế hoạch Dự án
                </a>
            @endif
            @if($canViewReport)
                <a class="button secondary" href="{{ route('workspaces.dashboard',[$workspace,'project_id'=>$project->id]) }}">
                    📈 Dashboard / Báo cáo
                </a>
            @endif
            @can('manageMembers',$project)
                <a class="button secondary" href="{{ route('workspaces.projects.members.index',[$workspace,$project]) }}">
                    👥 Thành viên Dự án
                </a>
            @endcan
            @can('update',$project)
                <a class="button secondary" href="{{ route('workspaces.projects.edit',[$workspace,$project]) }}">
                    ⚙️ Sửa thông tin
                </a>
            @endcan
            @if($canViewIntegration)
                <a class="button secondary" href="{{ route('workspaces.projects.github.show',[$workspace,$project]) }}">
                    🐙 GitHub Logs
                </a>
            @endif
        </div>
    </div>
</section>

@include('projects._guide')

<!-- Recent Tasks Section with Quick Edit -->
@if($canViewTasks)
<section class="card" style="margin-top: 1.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h2 style="margin: 0; font-size: 1.25rem;">Công việc trong Dự án</h2>
            <p class="muted" style="margin: 0.2rem 0 0; font-size: 0.85rem;">Các công việc gần đây, có thể sửa nhanh trực tiếp ngay tại đây</p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            @if($canCreateTask)
                <a class="button" data-task-panel href="{{ route('workspaces.projects.tasks.create',[$workspace,$project]) }}">+ Tạo công việc</a>
            @endif
            <a href="{{ route('workspaces.projects.tasks.index',[$workspace,$project]) }}" class="button secondary" style="font-size: 0.825rem; padding: 0.35rem 0.75rem;">
                Xem tất cả ({{ $project->tasks_count ?? count($recentTasks) }}) ›
            </a>
        </div>
    </div>

    @if(count($recentTasks) > 0)
        @php
            $membersList = collect($people ?? [])->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'email' => $p->user->email ?? '',
            ])->values();
        @endphp
        <script id="workspace-members-data" type="application/json">{!! json_encode($membersList) !!}</script>
        <script id="deadline-presets-data" type="application/json">{!! json_encode($deadlinePresets ?? []) !!}</script>
        <div class="table-wrap mobile-data-table">
            <table>
                <thead>
                    <tr>
                        <th>Mã & Tiêu đề</th>
                        <th>Trạng thái</th>
                        <th>Người thực hiện</th>
                        <th>Hạn chót</th>
                        <th style="text-align: right;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTasks as $task)
                        @php($deadlineFormatted = $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') : null)
                        @php($deadlineLocal = $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') : '')
                        @php($isOverdue = $task->deadline_at?->isPast() && !in_array($task->status->value, ['DONE', 'CANCELLED']))
                        <tr data-task-id="{{ $task->id }}"
                            data-code="{{ $task->code }}"
                            data-title="{{ $task->title }}"
                            data-status="{{ $task->status->value }}"
                            data-priority="{{ $task->priority->value }}"
                            data-assignee-id="{{ $task->assignee_id }}"
                            data-reviewer-id="{{ $task->reviewer_id }}"
                            data-deadline="{{ $deadlineLocal }}"
                            data-estimate="{{ $task->estimate_minutes }}"
                            data-revision="{{ $task->revision }}"
                            data-update-url="{{ route('workspaces.projects.tasks.update', [$workspace, $project, $task]) }}"
                            data-status-url="{{ route('workspaces.projects.tasks.status.update', [$workspace, $project, $task]) }}"
                            data-can-update="{{ auth()->user()->can('update', $task) ? '1' : '0' }}"
                            data-can-status="{{ (auth()->user()->can('execute', $task) || auth()->user()->can('review', $task)) ? '1' : '0' }}"
                            data-can-assign="{{ (auth()->user()->can('update', $task) && ($canAssign ?? true)) ? '1' : '0' }}">
                            <td>
                                <a data-task-panel href="{{ route('workspaces.projects.tasks.show',[$workspace,$project,$task]) }}" style="text-decoration: none; font-weight: 700;">
                                    <span class="task-row-code" style="color: var(--primary); font-family: var(--font-mono); margin-right: 0.35rem;">{{ $task->code }}</span>
                                    <span class="task-row-title">{{ $task->title }}</span>
                                </a>
                                <div style="margin-top: 3px;">
                                    @can('update', $task)
                                        <button type="button" class="inline-edit-trigger inline-priority-trigger" data-inline-field="priority" title="Nhấp để đổi nhanh độ ưu tiên">
                                            <span class="inline-priority-indicator priority-{{ strtolower($task->priority->value) }}">●</span>
                                            <span style="font-size: 11px;">Ưu tiên: <strong class="inline-priority-text">{{ \App\Support\UiLabels::text($task->priority) }}</strong></span>
                                            <span class="inline-caret" aria-hidden="true">▾</span>
                                        </button>
                                    @else
                                        <span class="muted" style="font-size: 11px;">Ưu tiên: {{ \App\Support\UiLabels::text($task->priority) }}</span>
                                    @endcan
                                </div>
                            </td>
                            <td class="task-status-cell">
                                @if(auth()->user()->can('execute', $task) || auth()->user()->can('review', $task))
                                    <button type="button" class="inline-edit-trigger inline-status-trigger" data-inline-field="status" aria-label="Đổi nhanh trạng thái: {{ $task->title }}" title="Nhấp để đổi nhanh trạng thái">
                                        <span class="status-pill status-{{ strtolower(str_replace('_','-',$task->status->value)) }}">
                                            {{ \App\Support\UiLabels::text($task->status->value) }}
                                        </span>
                                        <span class="inline-caret" aria-hidden="true">▾</span>
                                    </button>
                                @else
                                    <span class="status-pill status-{{ strtolower(str_replace('_','-',$task->status->value)) }}">
                                        {{ \App\Support\UiLabels::text($task->status->value) }}
                                    </span>
                                @endif
                            </td>
                            <td class="task-assignee-cell">
                                @if(auth()->user()->can('update', $task) && ($canAssign ?? true))
                                    <button type="button" class="inline-edit-trigger inline-assignee-trigger" data-inline-field="assignee" title="Nhấp để phân công người thực hiện">
                                        <span class="inline-assignee-avatar" aria-hidden="true">
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
                                    <div class="task-row-assignee">
                                        @if($task->assignee)
                                            <span>{{ $task->assignee->name }}</span>
                                        @else
                                            <span class="muted" style="font-style: italic; font-size: 0.8rem;">Chưa phân công</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="task-deadline-cell">
                                @if(auth()->user()->can('update', $task))
                                    <button type="button" class="inline-edit-trigger inline-deadline-trigger" data-inline-field="deadline" title="Nhấp để đổi hạn hoàn thành">
                                        <span class="inline-deadline-icon" aria-hidden="true">📅</span>
                                        <span @class(['inline-deadline-text', 'task-overdue' => $isOverdue]) style="font-family: var(--font-mono); font-size: 0.8rem;">
                                            {{ $deadlineFormatted ?? 'Chưa có' }}
                                        </span>
                                        <span class="inline-caret" aria-hidden="true">▾</span>
                                    </button>
                                @else
                                    <div class="task-row-deadline">
                                        @if($task->deadline_at)
                                            <span @class(['task-overdue' => $isOverdue]) style="font-family: var(--font-mono); font-size: 0.8rem;">{{ $deadlineFormatted }}</span>
                                        @else
                                            <span class="muted" style="font-size: 0.8rem;">Chưa có</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                @can('update', $task)
                                    <a class="button secondary compact" data-task-panel href="{{ route('workspaces.projects.tasks.edit',[$workspace,$project,$task]) }}">Sửa</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted" style="margin: 0.5rem 0 0;">Chưa có công việc nào trong dự án này.</p>
    @endif
</section>
@endif

<!-- Sub-projects Section -->
<section class="card">
    <h2>Dự án con (Sub-projects)</h2>
    @if(count($children) > 0)
        <div class="grid" style="margin-top: 1rem;">
            @foreach($children as $child)
                <article class="card" style="margin: 0; background: var(--bg-subtle) !important;">
                    <h3 style="margin: 0 0 0.5rem 0;">
                        <a href="{{ route('workspaces.projects.show',[$workspace,$child]) }}">{{ $child->name }}</a>
                    </h3>
                    <p class="muted" style="margin: 0; font-size: 0.85rem;">Mở để xem chi tiết dự án con ›</p>
                </article>
            @endforeach
        </div>
    @else
        <p class="muted">Không có project con nào trong phạm vi bạn được xem.</p>
    @endif
</section>

<!-- Archival & Deletion Options -->
@can('archive',$project)
    <details class="card">
        <summary style="font-weight: 700; cursor: pointer;">Lưu trữ hoặc Khôi phục Dự án</summary>
        <p class="muted" style="margin-top: 0.75rem;">Khi lưu trữ, các thành viên vẫn có thể xem nội dung nhưng không thể chỉnh sửa.</p>
        <form method="POST" action="{{ route($project->archived_at?'workspaces.projects.restore':'workspaces.projects.archive',[$workspace,$project]) }}" data-form="project-archive" data-confirm="Xác nhận thay đổi lưu trữ project này?">
            @csrf
            <button type="submit" class="secondary">{{ $project->archived_at?'Khôi phục dự án':'Lưu trữ project' }}</button>
        </form>
    </details>
@endcan

@can('delete',$project)
    <details class="card" style="border-left: 4px solid var(--danger);">
        <summary style="font-weight: 700; cursor: pointer; color: var(--danger);">Xóa dự án nháp</summary>
        <p class="muted" style="margin-top: 0.75rem;">Chỉ có thể xóa dự án nháp còn trống, chưa có công việc hoặc dữ liệu liên quan.</p>
        <form method="POST" action="{{ route('workspaces.projects.destroy',[$workspace,$project]) }}" data-form="project-delete" data-confirm="Xóa vĩnh viễn project nháp trống này?">
            @csrf
            @method('DELETE')
            <button class="danger" type="submit">Xóa vĩnh viễn dự án</button>
        </form>
    </details>
@endcan

@if($canViewTasks)

@endif
</x-layout>
