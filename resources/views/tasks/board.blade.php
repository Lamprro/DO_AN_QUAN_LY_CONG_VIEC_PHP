@push('styles')
    <link rel="stylesheet" href="{{ asset('css/jira-board.css') }}?v={{ filemtime(public_path('css/jira-board.css')) }}">
@endpush
@push('scripts')
    <script src="{{ asset('js/jira-board.js') }}?v={{ filemtime(public_path('js/jira-board.js')) }}" defer></script>
@endpush

<x-layout title="Bảng công việc">
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ $project?->name ?? $workspace->name }}</p>
            <h1>Bảng công việc</h1>
            <p class="muted">Theo dõi tiến độ, tập trung ưu tiên và kéo thả thẻ để chuyển trạng thái nhanh.</p>
        </div>
        @if($canCreate)
            <a class="button" data-task-panel href="{{ route($routePrefix.'.create', $routeParams) }}">+ Tạo công việc</a>
        @endif
    </div>
    @include('tasks._view-nav')
@include('tasks._overview-note')

    <section class="jira-board" data-jira-board data-user-id="{{ auth()->id() }}" aria-label="Bảng Kanban">
        <div class="jira-toolbar">
            <div class="jira-chips" role="group" aria-label="Lọc nhanh các thẻ đã tải">
                @foreach(['all' => 'Tất cả', 'mine' => 'Chỉ việc của tôi', 'high' => 'Ưu tiên cao', 'overdue' => 'Quá hạn', 'unassigned' => 'Chưa phân công'] as $key => $label)
                    <button type="button" class="jira-chip" data-quick-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="jira-tools">
                <label class="jira-search">
                    <span class="sr-only">Tìm kiếm nhanh trong bảng</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
                    <input type="search" data-quick-search placeholder="Tìm kiếm nhanh trong bảng..." autocomplete="off" maxlength="200" aria-describedby="jira-filter-help">
                </label>
                <button type="button" class="jira-filter-toggle" data-advanced-toggle aria-controls="jira-advanced-filters" aria-expanded="true">Bộ lọc nâng cao</button>
            </div>
        </div>
        <div id="jira-advanced-filters" data-advanced-filters data-has-filters="{{ collect($filters)->except('view')->isNotEmpty() ? 'true' : 'false' }}">
            @include('tasks._filters')
        </div>
        <div class="jira-board-summary">
            <p class="result-summary" role="status" data-board-summary><strong>{{ collect($board)->sum(fn ($column) => $column->total()) }}</strong> công việc và cột mốc</p>
            <p id="jira-filter-help" class="muted">Lọc nhanh áp dụng trên thẻ đã tải (10 thẻ/cột mỗi trang). Dùng Bộ lọc nâng cao để tìm kiếm toàn bộ.</p>
        </div>
        <noscript><p class="notice">Bật JavaScript để kéo thả và lọc tức thì. Bạn vẫn có thể dùng bộ lọc nâng cao và nút đổi trạng thái.</p></noscript>
        <div class="board-scroll jira-scroll" tabindex="0" role="region" aria-label="Các cột công việc; cuộn ngang để xem">
            <div class="jira-columns" data-page="task-board">
                @foreach($board as $status => $column)
                    <section class="jira-column" data-status="{{ $status }}" data-total="{{ $column->total() }}" aria-labelledby="jira-heading-{{ $status }}">
                        <header class="jira-column-header">
                            <h2 id="jira-heading-{{ $status }}">{{ \App\Support\UiLabels::text($status) }}</h2>
                            <span class="jira-count" data-column-count title="Tổng công việc trong cột">{{ $column->total() }}</span>
                        </header>
                        <div class="jira-drop-zone" data-drop-zone>
                            @foreach($column as $task)
@php
    $itemRoutePrefix = $task->project_id ? 'workspaces.projects.tasks' : 'workspaces.tasks';
    $itemRouteParams = $task->project_id ? [$task->workspace_id, $task->project_id] : [$task->workspace_id];
@endphp
                                @php
                                    $canMove = auth()->user()->can('execute', $task) || auth()->user()->can('review', $task);
                                    $terminal = in_array($task->status->value, ['DONE', 'CANCELLED'], true);
                                    $overdue = !$terminal && $task->deadline_at?->isPast();
                                    $dueSoon = !$terminal && !$overdue && $task->deadline_at?->lessThanOrEqualTo(now()->addDay());
                                    $priority = $task->priority->value;
                                    $assignee = $task->assignee?->name;
                                    $initials = $assignee ? mb_strtoupper(collect(preg_split('/\s+/u', trim($assignee)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')) : '?';
                                @endphp
                                <article class="jira-card" draggable="{{ $canMove ? 'true' : 'false' }}"
                                    data-task-id="{{ $task->id }}" data-code="{{ $task->code }}" data-title="{{ $task->title }}"
                                    data-current-status="{{ $task->status->value }}" data-assignee-id="{{ $task->assignee_id }}"
                                    data-priority="{{ $priority }}" data-is-overdue="{{ $overdue ? 'true' : 'false' }}"
                                    data-deadline="{{ $task->deadline_at?->toIso8601String() }}"
                                    data-status-url="{{ route($itemRoutePrefix.'.status.update', [...$itemRouteParams, $task]) }}">
                                    <div class="jira-card-top">
                                        <span class="jira-type" title="{{ \App\Support\UiLabels::text($task->task_type) }}" aria-label="{{ \App\Support\UiLabels::text($task->task_type) }}">{{ $task->task_type->value === 'MILESTONE' ? '🚩' : '📋' }}</span>
                                        <strong class="jira-code">{{ $task->code }}</strong>
                                        <span class="jira-priority" data-level="{{ $priority }}" title="Ưu tiên: {{ \App\Support\UiLabels::text($task->priority) }}">
                                            <span aria-hidden="true">{{ ['urgent' => '⇈', 'high' => '↑', 'medium' => '=', 'low' => '↓'][$priority] }}</span>
                                            <span class="sr-only">Ưu tiên {{ \App\Support\UiLabels::text($task->priority) }}</span>
                                        </span>
                                    </div>
                                    <h3><a class="jira-title" data-task-panel draggable="false" href="{{ route($itemRoutePrefix.'.show', [...$itemRouteParams, $task]) }}" title="{{ $task->title }}">{{ $task->title }}</a></h3>
                                    @include('tasks._source', ['sourceTask' => $task])
                                    <div class="jira-card-bottom">
                                        @if($task->deadline_at)
                                            <span @class(['jira-deadline', 'is-overdue' => $overdue, 'is-due-soon' => $dueSoon]) data-deadline-badge title="Hạn: {{ $task->deadline_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') }} ({{ $workspace->timezone }})">
                                                <span aria-hidden="true">⏰</span>
                                                <span data-deadline-label>{{ $overdue ? 'Quá hạn • ' : ($dueSoon ? 'Sắp đến hạn • ' : '') }}</span>{{ $task->deadline_at->setTimezone($workspace->timezone)->format('d/m') }}
                                            </span>
                                        @endif
                                        @if($task->children_count)
                                            <span class="jira-subtasks" title="Công việc con hoàn thành" aria-label="{{ $task->completed_children_count }} trên {{ $task->children_count }} công việc con hoàn thành"><span aria-hidden="true">☑️</span> {{ $task->completed_children_count }}/{{ $task->children_count }}</span>
                                        @endif
                                        <span class="jira-avatar" data-tone="{{ $assignee ? (ord(substr($task->assignee_id, -1)) % 4) : 'none' }}" title="{{ $assignee ?? 'Chưa phân công' }}" aria-label="{{ $assignee ?? 'Chưa phân công' }}">{{ $initials }}</span>
                                    </div>
                                    <div class="jira-card-actions">
                                        @can('update', $task)
                                            <a data-task-panel draggable="false" href="{{ route($itemRoutePrefix.'.edit', [...$itemRouteParams, $task]) }}">Sửa</a>
                                        @endcan
                                        @if($canMove)
                                            <a data-task-panel draggable="false" href="{{ route($itemRoutePrefix.'.status.edit', [...$itemRouteParams, $task]) }}">Đổi trạng thái <span aria-hidden="true">↗</span></a>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                            <p class="jira-empty" data-column-empty @if($column->count()) hidden @endif>Chưa có công việc</p>
                        </div>
                        <div class="jira-pagination">{{ $column->links() }}</div>
                    </section>
                @endforeach
            </div>
        </div>
    </section>
</x-layout>
