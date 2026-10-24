<x-layout title="Công việc">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $project?->name ?? $workspace->name }}</p>
        <h1 style="margin: 0;">Quản lý Công việc</h1>
        <p class="muted" style="margin-top: 0.25rem;">Danh sách công việc, phân quyền thực hiện và theo dõi tiến độ</p>
    </div>
    <div class="actions">
        @if($canCreate)
            <a class="button" href="{{ route($routePrefix.'.create',$routeParams) }}">
                <span>+</span> Tạo công việc
            </a>
        @endif
    </div>
</div>

@include('tasks._view-nav')
@include('tasks._filters')
<details class="card task-tools" @if($errors->any()) open @endif><summary>Bộ lọc đã lưu và cập nhật hàng loạt</summary><div class="task-tools-content">@include('tasks._utilities')</div></details>
<p class="result-summary" role="status"><strong>{{ $tasks->total() }}</strong> công việc phù hợp · {{ $tasks->firstItem()??0 }}–{{ $tasks->lastItem()??0 }} trên trang này</p>

<!-- Task Data Table -->
<section class="card table-wrap mobile-data-table" data-page="tasks-index">
    <table>
        <thead>
            <tr>
                <th style="width: 100px;">Chọn</th>
                <th>Mã / Tiêu đề Công việc</th>
                <th>Phân loại & Trạng thái</th>
                <th>Người thực hiện</th>
                <th>Hạn hoàn thành</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tasks as $task)
                <tr data-task-id="{{ $task->id }}">
                    <td data-label="Chọn">
                        @if(auth()->user()->can('update',$task)||auth()->user()->can('archive',$task))
                            <label style="display: flex; align-items: center; gap: 0.4rem; margin: 0; cursor: pointer; font-family: var(--font-mono); font-size: 0.8rem;">
                                <input type="checkbox" form="bulk-tasks" name="items[{{ $task->id }}]" value="{{ $task->revision }}">
                                <span>{{ $task->code }}</span>
                            </label>
                        @else
                            <span class="muted" style="font-size: 0.75rem;">Chỉ đọc</span>
                        @endif
                    </td>
                    <td data-label="Công việc">
                        <div style="font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                            <a href="{{ route($routePrefix.'.show',[...$routeParams,$task]) }}" style="text-decoration: none;">
                                <span style="font-family: var(--font-mono); color: var(--primary); font-weight: 700; margin-right: 0.4rem;">{{ $task->code }}</span>
                                {{ $task->title }}
                            </a>
                            @can('update', $task)
                                <a href="{{ route($routePrefix.'.edit',[...$routeParams,$task]) }}" class="preset-chip" style="font-size: 0.72rem; padding: 0.15rem 0.45rem;" title="Sửa công việc">
                                    ✏️ Sửa
                                </a>
                            @endcan
                        </div>
                    </td>
                    <td data-label="Trạng thái">
                        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                            <span class="badge" style="font-size: 0.7rem; font-weight: 700;">
                                {{ \App\Support\UiLabels::text($task->task_type->value) }}
                            </span>
                            <span class="status-pill status-{{ strtolower(str_replace('_','-',$task->status->value)) }}">
                                {{ \App\Support\UiLabels::text($task->status->value) }}
                            </span>
                            <span class="badge" style="font-size: 0.7rem;">
                                {{ \App\Support\UiLabels::text($task->priority->value) }}
                            </span>
                            @if($task->archived_at)
                                <span class="badge" style="background: #fff1f2; color: #be123c;">Đã lưu trữ</span>
                            @endif
                        </div>
                    </td>
                    <td data-label="Phân công">
                        @if($task->assignee)
                            <div style="display: flex; align-items: center; gap: 0.4rem;">
                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--primary-light); color: var(--primary); font-size: 0.7rem; font-weight: 700;">
                                    {{ mb_substr($task->assignee->name, 0, 1) }}
                                </span>
                                <span>{{ $task->assignee->name }}</span>
                            </div>
                        @else
                            <span class="muted" style="font-style: italic;">Chưa phân công</span>
                        @endif
                    </td>
                    <td data-label="Hạn hoàn thành">
                        @if($task->deadline_at)
                            <span style="font-family: var(--font-mono); font-size: 0.85rem; {{ $task->deadline_at->isPast() && in_array($task->status->value, ['TODO', 'IN_PROGRESS', 'REVIEW']) ? 'color: var(--danger); font-weight: 700;' : '' }}">
                                {{ $task->deadline_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') }}
                            </span>
                        @else
                            <span class="muted">Chưa có hạn</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3rem 1rem;">
                        <p style="font-size: 1.75rem; margin-bottom: 0.5rem;">📋</p>
                        <h3>Chưa có công việc nào phù hợp</h3>
                        <p class="muted">Thử thay đổi bộ lọc hoặc tạo công việc mới để bắt đầu theo dõi.</p>
                        @if($canCreate)
                            <div style="margin-top: 1rem;">
                                <a class="button" href="{{ route($routePrefix.'.create',$routeParams) }}">Tạo công việc</a>
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
</x-layout>
