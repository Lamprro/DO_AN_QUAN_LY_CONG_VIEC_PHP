<x-layout title="Công việc">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $project?->name ?? $workspace->name }}</p>
        <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em;">Quản lý Công việc</h1>
        <p class="muted" style="margin-top: 0.25rem;">Danh sách công việc, phân quyền thực hiện và chỉnh sửa nhanh trực tiếp</p>
    </div>
    <div class="actions" style="display: flex; gap: 0.5rem; align-items: center;">
        @if($canCreate)
            <button type="button" class="button" id="btn-trigger-quick-create" style="background: var(--primary); color: #fff; font-weight: 600; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);">
                <span>⚡</span> Tạo nhanh
            </button>
            <a class="button secondary" href="{{ route($routePrefix.'.create',$routeParams) }}" title="Tạo công việc với đầy đủ trường">
                <span>+</span> Chi tiết
            </a>
        @endif
    </div>
</div>

@include('tasks._view-nav')
@include('tasks._filters')
<details class="card task-tools" @if($errors->any()) open @endif><summary>Bộ lọc đã lưu và cập nhật hàng loạt</summary><div class="task-tools-content">@include('tasks._utilities')</div></details>
<p class="result-summary" role="status"><strong>{{ $tasks->total() }}</strong> công việc phù hợp · {{ $tasks->firstItem()??0 }}–{{ $tasks->lastItem()??0 }} trên trang này</p>

<!-- Task Data Table -->
<section class="card table-wrap mobile-data-table" data-page="tasks-index" style="border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden;">
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Chọn</th>
                <th>Mã & Tiêu đề Công việc</th>
                <th>Phân loại & Trạng thái</th>
                <th>Người thực hiện</th>
                <th>Hạn hoàn thành</th>
                <th style="text-align: right; min-width: 130px;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tasks as $task)
                @php($deadlineFormatted = $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') : null)
                @php($deadlineLocal = $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') : '')
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
                    data-update-url="{{ route($routePrefix.'.update', [...$routeParams, $task]) }}"
                    data-status-url="{{ route($routePrefix.'.status.update', [...$routeParams, $task]) }}">
                    
                    <td data-label="Chọn">
                        @if(auth()->user()->can('update',$task)||auth()->user()->can('archive',$task))
                            <label style="display: flex; align-items: center; gap: 0.4rem; margin: 0; cursor: pointer; font-family: var(--font-mono); font-size: 0.8rem;">
                                <input type="checkbox" form="bulk-tasks" name="items[{{ $task->id }}]" value="{{ $task->revision }}">
                                <span class="muted">{{ $task->code }}</span>
                            </label>
                        @else
                            <span class="muted" style="font-size: 0.75rem;">Chỉ đọc</span>
                        @endif
                    </td>

                    <td data-label="Công việc">
                        <div style="font-weight: 700; font-size: 0.95rem;">
                            <a href="{{ route($routePrefix.'.show',[...$routeParams,$task]) }}" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;" class="task-title-link">
                                <span class="task-row-code" style="font-family: var(--font-mono); color: var(--primary); font-weight: 700;">{{ $task->code }}</span>
                                <span class="task-row-title">{{ $task->title }}</span>
                            </a>
                        </div>
                    </td>

                    <td data-label="Trạng thái">
                        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                            <span class="badge" style="font-size: 0.7rem; font-weight: 700;">
                                {{ \App\Support\UiLabels::text($task->task_type->value) }}
                            </span>
                            
                            <!-- Interactive Status Dropdown -->
                            <div class="interactive-status-wrap" style="position: relative; display: inline-block;">
                                <button type="button" class="status-pill status-{{ strtolower(str_replace('_','-',$task->status->value)) }} interactive-status-pill"
                                        title="Bấm để đổi nhanh trạng thái"
                                        data-task-id="{{ $task->id }}"
                                        data-current-status="{{ $task->status->value }}"
                                        style="cursor: pointer; border: none; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                    <span class="status-text">{{ \App\Support\UiLabels::text($task->status->value) }}</span>
                                    <span style="font-size: 0.6rem; opacity: 0.7;">▾</span>
                                </button>
                                <div class="status-dropdown-menu card" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; z-index: 50; min-width: 160px; padding: 0.35rem; box-shadow: var(--shadow-lg); border-radius: var(--radius-md);">
                                    @foreach(\App\Enums\TaskStatus::cases() as $st)
                                        <button type="button" class="status-option-btn" data-status="{{ $st->value }}"
                                                style="display: block; width: 100%; text-align: left; padding: 0.4rem 0.6rem; font-size: 0.8rem; border: none; background: transparent; border-radius: 4px; cursor: pointer; color: var(--text);">
                                            {{ \App\Support\UiLabels::text($st->value) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <span class="badge task-row-priority" style="font-size: 0.7rem;">
                                {{ \App\Support\UiLabels::text($task->priority->value) }}
                            </span>

                            @if($task->archived_at)
                                <span class="badge" style="background: #fff1f2; color: #be123c;">Đã lưu trữ</span>
                            @endif
                        </div>
                    </td>

                    <td data-label="Phân công">
                        <div class="task-row-assignee" style="display: flex; align-items: center; gap: 0.4rem;">
                            @if($task->assignee)
                                <span class="avatar-circle" style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--primary-light); color: var(--primary); font-size: 0.75rem; font-weight: 700;">
                                    {{ mb_substr($task->assignee->name, 0, 1) }}
                                </span>
                                <span>{{ $task->assignee->name }}</span>
                            @else
                                <span class="muted" style="font-style: italic; font-size: 0.85rem;">Chưa phân công</span>
                            @endif
                        </div>
                    </td>

                    <td data-label="Hạn hoàn thành">
                        <div class="task-row-deadline">
                            @if($task->deadline_at)
                                <span style="font-family: var(--font-mono); font-size: 0.85rem; {{ $task->deadline_at->isPast() && in_array($task->status->value, ['TODO', 'IN_PROGRESS', 'REVIEW']) ? 'color: var(--danger); font-weight: 700;' : '' }}">
                                    {{ $deadlineFormatted }}
                                </span>
                            @else
                                <span class="muted" style="font-size: 0.85rem;">Chưa có hạn</span>
                            @endif
                        </div>
                    </td>

                    <td data-label="Thao tác" style="text-align: right;">
                        <div style="display: inline-flex; align-items: center; gap: 0.35rem; justify-content: flex-end;">
                            @can('update', $task)
                                <button type="button" class="btn-quick-edit preset-chip" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; cursor: pointer; background: var(--primary-light); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.25); font-weight: 600;" title="Sửa nhanh ngay tại trang này">
                                    ⚡ Sửa
                                </button>
                                <a href="{{ route($routePrefix.'.edit',[...$routeParams,$task]) }}" class="preset-chip" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; text-decoration: none;" title="Mở trang sửa đầy đủ">
                                    ✏️
                                </a>
                            @endcan
                            <a href="{{ route($routePrefix.'.show',[...$routeParams,$task]) }}" class="preset-chip" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; text-decoration: none;" title="Xem chi tiết">
                                👁️
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 3rem 1rem;">
                        <p style="font-size: 2rem; margin-bottom: 0.5rem;">📋</p>
                        <h3 style="margin-bottom: 0.25rem;">Chưa có công việc nào phù hợp</h3>
                        <p class="muted">Thử thay đổi bộ lọc hoặc tạo công việc mới để bắt đầu theo dõi.</p>
                        @if($canCreate)
                            <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: center;">
                                <button type="button" class="button" id="btn-trigger-quick-create-empty">⚡ Tạo nhanh công việc</button>
                                <a class="button secondary" href="{{ route($routePrefix.'.create',$routeParams) }}">Tạo đầy đủ</a>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>

<div style="margin-top: 1.5rem;">
    {{ $tasks->links() }}
</div>

@include('tasks._quick-modal')
</x-layout>
