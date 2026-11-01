<x-layout :title="$task->title">
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/jira-task.css') }}">
    <style>
        .jira-issue-view { grid-template-columns: minmax(0, 1fr) 320px; }
        .jira-issue-main, .jira-issue-sidebar { min-width: 0; overflow-wrap: anywhere; }
        .jira-issue-main > .card, .jira-issue-main .jira-section > .card { margin: 0; }
        .jira-issue-code-bar { flex-wrap: wrap; }
        .jira-issue-code { background: #e9f2ff; color: #0c66e4; }
        .jira-issue-title { font-size: clamp(1.5rem, 2.2vw, 2rem); font-weight: 750; }
        .jira-issue-main .breadcrumbs { margin: 0; flex-wrap: wrap; }
        .jira-action-toolbar { padding: 0; }
        .jira-action-toolbar form { margin: 0; }
        .jira-action-btn:focus-visible, .jira-tab-btn:focus-visible { outline: 2px solid #0c66e4; outline-offset: 3px; }
        .jira-section { display: grid; gap: 16px; min-width: 0; }
        .jira-section > h2, .jira-activity-heading { margin: 0; font-size: 1.1rem; }
        .jira-subtask-list { list-style: none; padding: 0; margin: 12px 0 0; }
        .jira-subtask-list li { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-top: 1px solid var(--border); }
        .jira-subtask-list a { min-width: 0; }
        .jira-subtask-list .status-pill { flex-shrink: 0; }
        .jira-detail-row { gap: 12px; align-items: flex-start; }
        .jira-detail-label { flex: 0 0 43%; }
        .jira-detail-value { min-width: 0; text-align: right; justify-content: flex-end; flex-wrap: wrap; }
        .jira-person { flex-wrap: nowrap; }
        .jira-person-avatar { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; flex: 0 0 28px; border-radius: 50%; background: #e9f2ff; color: #0c66e4; font-size: 0.7rem; font-weight: 700; }
        .jira-sidebar-section + .jira-sidebar-section { border-top: 1px solid var(--border); padding-top: 16px; }
        .jira-sidebar-section h2 { margin: 0 0 12px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); }
        .jira-sidebar-section details + details { margin-top: 16px; }
        .jira-sidebar-section summary { font-weight: 600; cursor: pointer; }
        .jira-overdue { color: var(--danger, #c9372c); }
        .jira-tabs { gap: 4px; overflow-x: auto; }
        .jira-tab-btn { white-space: nowrap; text-decoration: none; flex-shrink: 0; }
        .jira-tab-pane[aria-busy="true"] { opacity: 0.55; }
        .jira-tab-pane { transition: opacity 0.15s ease; }
        [data-jira-activity-type="HISTORY"] #task-discussion,
        [data-jira-activity-type="COMMENT"] #task-discussion > section:last-child,
        [data-jira-activity-type="ATTACHMENT"] #task-discussion > section:first-child { display: none; }
        .jira-issue-main #task-discussion { grid-template-columns: minmax(0, 1fr) !important; }
        .jira-issue-main #task-activities { margin: 16px 0 0 !important; }
        @media (max-width: 1100px) { .jira-issue-view { grid-template-columns: minmax(0, 1fr); } }
        @media (prefers-reduced-motion: reduce) { .jira-tab-pane { transition: none; } }
    </style>
@endpush

@php
    $canMove = auth()->user()->can('execute', $task) || auth()->user()->can('review', $task);
    $isOverdue = $task->deadline_at && $task->deadline_at->isPast() && in_array($task->status->value, ['TODO', 'IN_PROGRESS', 'REVIEW'], true);
    $activityTabs = ['' => 'Tất cả', 'COMMENT' => 'Bình luận', 'HISTORY' => 'Lịch sử thay đổi', 'ATTACHMENT' => 'Tệp đính kèm'];
    $activeActivityType = request('activity_type', '') ?: '';
@endphp

<div class="jira-issue-view" data-page="task-show" data-task-id="{{ $task->id }}">
    <div class="jira-issue-main">
        <nav class="breadcrumbs" aria-label="Đường dẫn công việc">
            <a href="{{ route('workspaces.show', $workspace) }}">{{ $workspace->name }}</a>
            <span aria-hidden="true">›</span>
            @if($project)
                <a href="{{ route('workspaces.projects.show', [$workspace, $project]) }}">{{ $project->name }}</a>
            @else
                <a href="{{ route($routePrefix.'.index', $routeParams) }}">Công việc cá nhân</a>
            @endif
            <span aria-hidden="true">›</span><span aria-current="page">{{ $task->code }}</span>
        </nav>

        <header class="jira-issue-header">
            <div class="jira-issue-code-bar">
                <span class="jira-issue-code">{{ $task->code }}</span>
                <span class="badge">{{ \App\Support\UiLabels::text($task->task_type->value) }}</span>
                <span class="status-pill status-{{ strtolower(str_replace('_', '-', $task->status->value)) }}">{{ \App\Support\UiLabels::text($task->status->value) }}</span>
                <span class="badge">Mức ưu tiên: {{ \App\Support\UiLabels::text($task->priority->value) }}</span>
                @if($task->archived_at)<span class="badge">Đã lưu trữ</span>@endif
            </div>
            <h1 class="jira-issue-title">{{ $task->title }}</h1>
        </header>

        <div class="jira-action-toolbar" role="group" aria-label="Thao tác công việc">
            @can('update', $task)
                <a class="jira-action-btn primary" data-task-panel data-panel-url="{{ route($routePrefix.'.edit', [...$routeParams, $task]) }}" href="{{ route($routePrefix.'.edit', [...$routeParams, $task]) }}">Sửa nhanh</a>
                <a class="jira-action-btn" href="{{ route($routePrefix.'.edit', [...$routeParams, $task]) }}">Chỉnh sửa đầy đủ</a>
            @endcan
            @if($canMove && $task->task_type->value !== 'GROUP')
                <a class="jira-action-btn" data-task-panel href="{{ route($routePrefix.'.status.edit', [...$routeParams, $task]) }}">Đổi trạng thái</a>
            @endif
            @if($task->task_type->value==='WORK' && $task->status->value==='IN_PROGRESS' && $task->assignee_id===auth()->id() && auth()->user()->can('execute',$task))
                <form method="post" action="{{ route('workspaces.timer.start', [$workspace, $task]) }}">
                    @csrf
                    <button type="submit" class="jira-action-btn">Bắt đầu bấm giờ</button>
                </form>
            @endif
            @if($task->assignee_id===auth()->id() && $task->task_type->value==='WORK' && in_array($task->status->value,['TODO','IN_PROGRESS','REVIEW'],true) && app(\App\Policies\TaskPolicy::class)->mutable(auth()->user(),$task,'task.update') && app(\App\Policies\CalendarEventPolicy::class)->scope(auth()->user(),$workspace,$project,'calendar.create'))
                <a class="jira-action-btn" href="{{ route('workspaces.calendar.create', [$workspace, 'project_id' => $project?->id, 'task_id' => $task->id]) }}">Xếp lịch</a>
            @endif
        </div>

        <section class="jira-desc-card" aria-labelledby="task-description-heading">
            <h2 id="task-description-heading" class="jira-activity-heading">Mô tả</h2>
            <p class="jira-desc-content">{{ filled($task->description) ? $task->description : 'Chưa có mô tả chi tiết' }}</p>
        </section>

        <section class="jira-section" aria-labelledby="task-subtasks-heading">
            <div class="jira-desc-card">
                <h2 id="task-subtasks-heading" class="jira-activity-heading">Công việc con &amp; Tiến độ <span class="badge">{{ $subtasks->count() }}</span></h2>
                <ul class="jira-subtask-list">
                    @forelse($subtasks as $subtask)
                        <li>
                            <a href="{{ route($routePrefix.'.show', [...$routeParams, $subtask]) }}"><span class="jira-issue-code">{{ $subtask->code }}</span> {{ $subtask->title }}</a>
                            <span class="status-pill status-{{ strtolower(str_replace('_', '-', $subtask->status->value)) }}">{{ \App\Support\UiLabels::text($subtask->status->value) }}</span>
                        </li>
                    @empty
                        <li class="muted">Chưa có công việc con.</li>
                    @endforelse
                </ul>
            </div>
            @include('tasks.workflow', ['jiraSection' => 'progress'])
        </section>

        <section class="jira-section" aria-labelledby="task-constraints-heading">
            <h2 id="task-constraints-heading">Ràng buộc / Chặn</h2>
            @include('tasks.workflow', ['jiraSection' => 'constraints'])
        </section>

        <section class="jira-desc-card" data-jira-activities data-jira-activity-type="{{ $activeActivityType }}" aria-labelledby="task-activity-heading">
            <h2 id="task-activity-heading" class="jira-activity-heading">Hoạt động</h2>
            <nav class="jira-tabs" role="tablist" aria-label="Hoạt động công việc">
                @foreach($activityTabs as $type => $label)
                    <a id="jira-tab-{{ $type ?: 'ALL' }}" class="jira-tab-btn {{ $activeActivityType === $type ? 'active' : '' }}" role="tab" aria-selected="{{ $activeActivityType === $type ? 'true' : 'false' }}" aria-controls="jira-activity-pane" tabindex="{{ $activeActivityType === $type ? '0' : '-1' }}" data-jira-tab="{{ $type }}" href="{{ route($routePrefix.'.show', [...$routeParams, $task, 'activity_type' => $type]) }}#task-activity-heading">{{ $label }}</a>
                @endforeach
            </nav>
            <div id="jira-activity-pane" class="jira-tab-pane active" role="tabpanel" aria-labelledby="jira-tab-{{ $activeActivityType ?: 'ALL' }}" tabindex="0">
                @include('tasks.workflow', ['jiraSection' => 'discussion'])
                @include('tasks.workflow', ['jiraSection' => 'activities'])
            </div>
            <p class="muted" role="status" data-jira-tab-status></p>
        </section>

        <details class="jira-desc-card">
            <summary>Luồng xử lý và phân tích hạn chót</summary>
            @include('tasks._journey')
            @include('tasks._deadline')
        </details>
        @include('tasks._collaboration')
    </div>

    <aside class="jira-issue-sidebar" aria-label="Thông tin công việc">
        <section class="jira-sidebar-section" aria-labelledby="task-assignment-heading">
            <h2 id="task-assignment-heading">Chi tiết phân công</h2>
            @foreach(['Người thực hiện' => $task->assignee, 'Người kiểm duyệt' => $task->reviewer, 'Người tạo' => $task->creator] as $label => $person)
                <div class="jira-detail-row">
                    <span class="jira-detail-label">{{ $label }}</span>
                    <span class="jira-detail-value jira-person">
                        @if($person)
                            <span class="jira-person-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($person->name, 0, 1)) }}</span>
                        @endif
                        <span>{{ $person?->name ?? 'Chưa phân công' }}</span>
                    </span>
                </div>
            @endforeach
        </section>

        <section class="jira-sidebar-section" id="task-time" tabindex="-1" aria-labelledby="task-time-heading">
            <h2 id="task-time-heading">Thời gian</h2>
            <div class="jira-detail-row">
                <span class="jira-detail-label">Ước lượng thời gian</span>
                <span class="jira-detail-value">{{ $task->estimate_minutes !== null ? $task->estimate_minutes.' phút' : 'Chưa có' }}</span>
            </div>
            <div class="jira-detail-row">
                <span class="jira-detail-label">Hạn hoàn thành</span>
                <span class="jira-detail-value {{ $isOverdue ? 'jira-overdue' : '' }}">
                    @if($task->deadline_at)
                        <time datetime="{{ $task->deadline_at->toIso8601String() }}">{{ $task->deadline_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') }}</time>
                    @else
                        Chưa có
                    @endif
                    @if($isOverdue)<small>Đã quá hạn</small>@endif
                </span>
            </div>
            <p class="muted"><small>Múi giờ: {{ $workspace->timezone }}</small></p>
            <a class="jira-action-btn" href="{{ route('timers.index') }}">Xem bộ đếm giờ của tôi</a>
        </section>

        @if(auth()->user()->can('archive', $task) || (auth()->user()->can('delete', $task) && $deletable))
            <section class="jira-sidebar-section" aria-labelledby="task-other-actions-heading">
                <h2 id="task-other-actions-heading">Hành động khác</h2>
                @can('archive', $task)
                    <details>
                        <summary>{{ $task->archived_at ? 'Khôi phục công việc' : 'Lưu trữ công việc' }}</summary>
                        <p class="muted">Lưu trữ để ẩn khỏi danh sách chính mà vẫn bảo toàn toàn bộ lịch sử.</p>
                        <form method="POST" action="{{ route($routePrefix.($task->archived_at ? '.restore' : '.archive'), [...$routeParams, $task]) }}" data-form="task-archive" data-confirm="Xác nhận thay đổi lưu trữ công việc này?">
                            @csrf
                            <button type="submit" class="secondary">{{ $task->archived_at ? 'Khôi phục' : 'Lưu trữ công việc' }}</button>
                        </form>
                    </details>
                @endcan
                @can('delete', $task)
                    @if($deletable)
                        <details>
                            <summary class="jira-overdue">Xóa công việc nháp</summary>
                            <p class="muted">Chỉ xóa được công việc chưa bắt đầu, chưa từng có lịch sử hoặc dữ liệu liên quan.</p>
                            <form method="POST" action="{{ route($routePrefix.'.destroy', [...$routeParams, $task]) }}" data-form="task-delete" data-confirm="Xóa vĩnh viễn công việc nháp này?">
                                @csrf
                                @method('DELETE')
                                <button class="danger" type="submit">Xóa vĩnh viễn</button>
                            </form>
                        </details>
                    @endif
                @endcan
            </section>
        @endif
    </aside>
</div>

@push('scripts')
<script>
(() => {
    let pendingRequest;
    // Delegation survives the shared task panel's refresh of main content.
    document.addEventListener('click', async (event) => {
        const tab = event.target.closest('[data-jira-tab]');
        if (!tab || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const root = tab.closest('[data-jira-activities]');
        const pane = root.querySelector('[role="tabpanel"]');
        pendingRequest?.abort();
        const request = new AbortController();
        pendingRequest = request;
        pane.setAttribute('aria-busy', 'true');
        root.querySelector('[data-jira-tab-status]').textContent = 'Đang tải hoạt động…';
        try {
            const response = await fetch(tab.href, { signal: request.signal, headers: { Accept: 'text/html' }, cache: 'no-store' });
            if (!response.ok || response.redirected) throw new Error('load');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const activities = page.querySelector('#task-activities');
            if (!activities) throw new Error('content');
            if (!root.isConnected || request.signal.aborted) return;
            const current = root.querySelector('#task-activities');
            // Never discard an unsaved edit in an activity form.
            if ([...current.querySelectorAll('textarea')].some(field => field.value !== field.defaultValue)) {
                root.querySelector('[data-jira-tab-status]').textContent = 'Hãy lưu bình luận đang sửa trước khi chuyển mục hoạt động.';
                return;
            }
            current.replaceWith(document.importNode(activities, true));
            root.dataset.jiraActivityType = tab.dataset.jiraTab;
            root.querySelectorAll('[data-jira-tab]').forEach((item) => {
                const selected = item === tab;
                item.classList.toggle('active', selected);
                item.setAttribute('aria-selected', String(selected));
                item.tabIndex = selected ? 0 : -1;
            });
            pane.setAttribute('aria-labelledby', tab.id);
            history.replaceState(history.state, '', tab.href);
            window.WorkUI?.mount(root.querySelector('#task-activities'));
            root.querySelector('[data-jira-tab-status]').textContent = 'Đã tải mục ' + tab.textContent.trim() + '.';
        } catch (error) {
            if (error.name !== 'AbortError') {
                root.querySelector('[data-jira-tab-status]').textContent = 'Không tải được hoạt động. Vui lòng thử lại hoặc mở liên kết trong thẻ mới.';
            }
        } finally {
            if (pendingRequest === request) pane.removeAttribute('aria-busy');
        }
    });
    document.addEventListener('keydown', (event) => {
        const tab = event.target.closest('[data-jira-tab]');
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const tabs = [...tab.closest('[role="tablist"]').querySelectorAll('[data-jira-tab]')];
        const index = tabs.indexOf(tab);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        tabs[next].focus();
        tabs[next].click();
    });
})();
</script>
@endpush
</x-layout>
