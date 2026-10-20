<x-layout title="Công việc">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $project?->name ?? $workspace->name }}</p>
        <h1 style="margin: 0;">Quản lý Công việc</h1>
        <p class="muted" style="margin-top: 0.25rem;">Danh sách công việc, phân quyền thực hiện và theo dõi tiến độ</p>
    </div>
    <div class="actions"><a class="button secondary" href="{{ route($routePrefix.'.index',array_merge($routeParams,$filters,['view'=>'board'])) }}">Bảng Kanban</a>
        <a class="button secondary" href="{{ route($routePrefix.'.tree',$routeParams) }}">
            🌳 Xem cây công việc
        </a>
        <a class="button secondary" href="{{ $project ? route('workspaces.projects.show',[$workspace,$project]) : route('workspaces.show',$workspace) }}">
            ‹ Về tổng quan
        </a>
        @if($canCreate)
            <a class="button" href="{{ route($routePrefix.'.create',$routeParams) }}">
                <span>+</span> Tạo task mới
            </a>
        @endif
    </div>
</div>

<!-- Preset Quick Filters -->
<div class="preset-bar">
    @foreach(['all'=>'📋 Tất cả việc','mine'=>'👤 Việc của tôi','review'=>'⏳ Chờ tôi duyệt','blocked'=>'🚫 Đang bị chặn','overdue'=>'⚠️ WORK quá hạn'] as $value=>$label)
        <a class="preset-chip {{ ($filters['preset']??'all') === $value ? 'active' : '' }}"
           href="{{ route($routePrefix.'.index', array_merge($routeParams, ['preset'=>$value])) }}">
            {{ $label }}
        </a>
    @endforeach
</div>

@include('tasks._utilities')

<!-- Search & Advanced Filter Form -->
<form method="GET" action="{{ route($routePrefix.'.index',$routeParams) }}" class="card" data-form="task-search" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: 1rem; align-items: end;">
        <div>
            <label for="preset">Bộ lọc nhanh</label>
            <select name="preset" id="preset">
                @foreach(['all'=>'Tất cả','mine'=>'Việc của tôi','review'=>'Chờ tôi duyệt','blocked'=>'Đang bị chặn','overdue'=>'WORK quá hạn'] as $key=>$label)
                    <option value="{{ $key }}" @selected(($filters['preset']??'all')===$key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input name="q" label="Tìm tiêu đề hoặc mã" :value="$filters['q']??''" placeholder="Nhập từ khóa hoặc mã..." />
        </div>
        <div>
            <label for="status">Trạng thái</label>
            <select id="status" name="status">
                <option value="">Tất cả trạng thái</option>
                @foreach(\App\Enums\TaskStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status']??'')===$s->value)>{{ \App\Support\UiLabels::text($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="priority">Mức ưu tiên</label>
            <select id="priority" name="priority">
                <option value="">Tất cả ưu tiên</option>
                @foreach(\App\Enums\TaskPriority::cases() as $p)
                    <option value="{{ $p->value }}" @selected(($filters['priority']??'')===$p->value)>{{ \App\Support\UiLabels::text($p) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="assignee_id">Người thực hiện</label>
            <select id="assignee_id" name="assignee_id">
                <option value="">Tất cả thành viên</option>
                @foreach($people as $person)
                    <option value="{{ $person->user_id }}" @selected(($filters['assignee_id']??'')===$person->user_id)>{{ $person->user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            @include('shared.list-controls',['sortOptions'=>['created_at'=>'Ngày tạo','code'=>'Mã task','title'=>'Tiêu đề','priority'=>'Mức ưu tiên','deadline_at'=>'Hạn hoàn thành']])
        </div>
        <div>
            <label for="per_page">Hiển thị mỗi trang</label>
            <select id="per_page" name="per_page">
                @foreach([10,25,50] as $n)
                    <option value="{{ $n }}" @selected((int)($filters['per_page']??10)===$n)>{{ $n }} dòng</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="actions" style="margin-top: 1.25rem; margin-bottom: 0;">
        <button type="submit">Lọc công việc</button>
        <a href="{{ route($routePrefix.'.index',$routeParams) }}" class="button secondary">Xóa bộ lọc</a>
    </div>
</form>

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
                        <div style="font-weight: 700; font-size: 0.95rem;">
                            <a href="{{ route($routePrefix.'.show',[...$routeParams,$task]) }}" style="text-decoration: none;">
                                <span style="font-family: var(--font-mono); color: var(--primary); font-weight: 700; margin-right: 0.4rem;">{{ $task->code }}</span>
                                {{ $task->title }}
                            </a>
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
                                <a class="button" href="{{ route($routePrefix.'.create',$routeParams) }}">Tạo task mới</a>
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
