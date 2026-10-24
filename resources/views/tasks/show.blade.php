<x-layout :title="$task->title">
<div style="margin-bottom: 1rem;">
    <a href="{{ route($routePrefix.'.index',$routeParams) }}" style="font-size: 0.875rem; font-weight: 600;">
        ‹ Danh sách công việc
    </a>
</div>

<section class="card" data-page="task-show" data-task-id="{{ $task->id }}" style="border-top: 4px solid var(--primary);">
    <div class="page-heading">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                <span style="font-family: var(--font-mono); font-weight: 800; font-size: 0.85rem; background: var(--primary-light); color: var(--primary); padding: 0.25rem 0.6rem; border-radius: var(--radius-sm); border: 1px solid rgba(99, 102, 241, 0.25);">
                    {{ $task->code }}
                </span>
                <span class="badge" style="font-weight: 700;">
                    {{ \App\Support\UiLabels::text($task->task_type->value) }}
                </span>
                <span class="status-pill status-{{ strtolower(str_replace('_','-',$task->status->value)) }}">
                    {{ \App\Support\UiLabels::text($task->status->value) }}
                </span>
                <span class="badge">
                    Mức ưu tiên: {{ \App\Support\UiLabels::text($task->priority->value) }}
                </span>
                @if($task->archived_at)
                    <span class="badge" style="background: #fff1f2; color: #be123c;">Đã lưu trữ</span>
                @endif
            </div>
            <h1 style="margin-bottom: 0.75rem; font-size: 1.6rem;">{{ $task->code }} — {{ $task->title }}</h1>
        </div>
    </div>

    @if($task->description)
        <div style="background: var(--bg-subtle); padding: 1.25rem; border-radius: var(--radius-md); margin: 1rem 0; border: 1px solid var(--border-light);">
            <p style="white-space: pre-wrap; margin: 0; color: var(--text); line-height: 1.6;">{{ $task->description }}</p>
        </div>
    @endif

    <!-- Metadata Grid -->
    <div class="kpi-grid" style="margin: 1.25rem 0;">
        <article class="kpi">
            <span>👤 Người tạo</span>
            <div style="font-size: 1.1rem; font-weight: 700; margin-top: 0.35rem; color: var(--text);">
                {{ $task->creator->name }}
            </div>
        </article>
        <article class="kpi">
            <span>🎯 Người thực hiện</span>
            <div style="font-size: 1.1rem; font-weight: 700; margin-top: 0.35rem; color: var(--text);">
                {{ $task->assignee?->name ?? 'Chưa phân công' }}
            </div>
        </article>
        <article class="kpi">
            <span>👁️ Người duyệt</span>
            <div style="font-size: 1.1rem; font-weight: 700; margin-top: 0.35rem; color: var(--text);">
                {{ $task->reviewer?->name ?? 'Chưa phân công' }}
            </div>
        </article>
        <article class="kpi">
            <span>⏱️ Ước lượng</span>
            <div style="font-size: 1.1rem; font-weight: 700; margin-top: 0.35rem; color: var(--text); font-family: var(--font-mono);">
                {{ $task->estimate_minutes ?? 'Chưa có' }} <small style="font-size: 0.8rem; font-weight: 500;">phút</small>
            </div>
        </article>
        <article class="kpi">
            <span>📅 Hạn hoàn thành</span>
            <div style="font-size: 1.05rem; font-weight: 700; margin-top: 0.35rem; color: {{ $task->deadline_at && $task->deadline_at->isPast() && in_array($task->status->value, ['TODO','IN_PROGRESS','REVIEW']) ? 'var(--danger)' : 'var(--text)' }}; font-family: var(--font-mono);">
                {{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i') ?? 'Chưa có' }}
            </div>
            <small class="muted" style="font-size: 0.75rem;">Múi giờ: {{ $workspace->timezone }}</small>
        </article>
    </div>

    <!-- Actions Toolbar -->
    <div class="actions">
        @can('update',$task)
            <button type="button" class="button" data-open-quick-edit style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 700;">
                ⚡ Sửa nhanh
            </button>
            <a class="button secondary" href="{{ route($routePrefix.'.edit',[...$routeParams,$task]) }}">
                📝 Chỉnh sửa đầy đủ
            </a>
        @endcan

        @if($task->assignee_id===auth()->id() && $task->task_type->value==='WORK' && in_array($task->status->value,['TODO','IN_PROGRESS','REVIEW'],true) && app(\App\Policies\TaskPolicy::class)->mutable(auth()->user(),$task,'task.update') && app(\App\Policies\CalendarEventPolicy::class)->scope(auth()->user(),$workspace,$project,'calendar.create'))
            <a class="button secondary" href="{{ route('workspaces.calendar.create',[$workspace,'project_id'=>$project?->id,'task_id'=>$task->id]) }}">
                📅 Xếp lịch cho công việc này
            </a>
        @endif
    </div>
</section>

@include('tasks._journey')

<!-- Deadlines & Insights -->
@include('tasks._deadline')

<!-- Workflow State Machine, Dependencies, Blockers, Activities -->
@include('tasks.workflow')

<!-- Time Tracking & Real Hours Section -->
<section id="task-time" tabindex="-1" class="card" style="border-left: 4px solid #0284c7;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">⏱️ Ghi nhận thời gian thực tế (Time Tracking)</h2>
            <p class="muted" style="margin: 0;">Theo dõi chính xác thời gian thực tế bạn dành cho công việc này.</p>
        </div>
        <div class="actions" style="margin: 0;">
            <a href="{{ route('timers.index') }}" class="button secondary">Xem tất cả Timer của tôi</a>
            @if($task->task_type->value==='WORK' && $task->status->value==='IN_PROGRESS' && $task->assignee_id===auth()->id() && auth()->user()->can('execute',$task))
                <form method="post" action="{{ route('workspaces.timer.start',[$workspace,$task]) }}" style="margin: 0;">
                    @csrf
                    <button style="background: var(--success) !important; color: #fff !important;">
                        ▶ Bắt đầu ghi giờ
                    </button>
                </form>
            @endif
        </div>
    </div>
</section>

<!-- Archival & Permanent Deletion -->
<div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
    @can('archive',$task)
        <details class="card" style="flex: 1; min-width: 280px; margin: 0;">
            <summary style="font-weight: 600; cursor: pointer;">{{ $task->archived_at ? 'Khôi phục công việc' : 'Lưu trữ công việc' }}</summary>
            <p class="muted" style="font-size: 0.85rem; margin: 0.75rem 0;">Lưu trữ để ẩn khỏi danh sách chính mà vẫn bảo toàn toàn bộ lịch sử.</p>
            <form method="POST" action="{{ route($routePrefix.($task->archived_at?'.restore':'.archive'),[...$routeParams,$task]) }}" data-form="task-archive" data-confirm="Xác nhận thay đổi lưu trữ task này?">
                @csrf
                <button type="submit" class="secondary">{{ $task->archived_at?'Khôi phục':'Lưu trữ task' }}</button>
            </form>
        </details>
    @endcan

    @can('delete',$task)
        @if($deletable)
            <details class="card" style="flex: 1; min-width: 280px; margin: 0; border-left: 4px solid var(--danger);">
                <summary style="font-weight: 600; cursor: pointer; color: var(--danger);">Xóa task nháp</summary>
                <p class="muted" style="font-size: 0.85rem; margin: 0.75rem 0;">Chỉ xóa được task TODO chưa từng có lịch sử hoặc dữ liệu liên quan.</p>
                <form method="POST" action="{{ route($routePrefix.'.destroy',[...$routeParams,$task]) }}" data-form="task-delete" data-confirm="Xóa vĩnh viễn task nháp này?">
                    @csrf
                    @method('DELETE')
                    <button class="danger" type="submit">Xóa vĩnh viễn</button>
                </form>
            </details>
        @endif
    @endcan
</div>

@include('tasks._collaboration')
@include('tasks._quick-edit')
</x-layout>
