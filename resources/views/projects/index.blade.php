<x-layout title="Danh sách Dự án">
<div class="page-heading" style="margin-bottom: 1.5rem;">
    <div>
        <p class="eyebrow">{{ $workspace->name }}</p>
        <h1 style="margin: 0;">Danh sách Dự án (Projects)</h1>
        <p class="muted" style="margin-top: 0.25rem;">Quản lý các dự án, tiến độ công việc và phân công thành viên</p>
    </div>
    <div class="actions">
        <a class="button secondary" href="{{ route('workspaces.show',$workspace) }}">‹ Về Workspace</a>
        @if($canCreate)
            <a class="button" href="{{ route('workspaces.projects.create',$workspace) }}">
                <span>+</span> Tạo dự án mới
            </a>
        @endif
    </div>
</div>

<form class="card" method="GET" action="{{ route('workspaces.projects.index',$workspace) }}" data-form="project-search" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 1rem; align-items: end;">
        <div>
            <x-input name="q" label="Tìm tên project" :value="$filters['q'] ?? ''" placeholder="Nhập tên dự án..." />
        </div>
        <div>
            <label for="status">Trạng thái dự án</label>
            <select name="status" id="status">
                <option value="">Tất cả trạng thái</option>
                @foreach(\App\Enums\ProjectStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status']??'')===$s->value)>{{ \App\Support\UiLabels::text($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            @include('shared.list-controls',['sortOptions'=>['created_at'=>'Ngày tạo','name'=>'Tên dự án','deadline'=>'Hạn hoàn thành']])
        </div>
        <div>
            <label for="project-view">Chế độ hiển thị</label>
            <select id="project-view" name="view">
                <option value="cards" @selected(($filters['view']??'cards')==='cards')>Dạng Thẻ (Cards)</option>
                <option value="table" @selected(($filters['view']??'cards')==='table')>Dạng Bảng (Table)</option>
            </select>
        </div>
    </div>
    <div class="actions" style="margin-top: 1rem; margin-bottom: 0;">
        <button type="submit">Áp dụng bộ lọc</button>
        <a href="{{ route('workspaces.projects.index',$workspace) }}" class="button secondary">Xóa lọc</a>
    </div>
</form>

@if(($filters['view']??'cards')==='table')
    <section class="card table-wrap mobile-data-table" data-page="projects-index">
        <table>
            <thead>
                <tr>
                    <th>Tên Dự Án</th>
                    <th>Trạng Thái</th>
                    <th>Công Việc</th>
                    <th>Hạn Hoàn Thành</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                    <tr>
                        <td data-label="Project">
                            <a href="{{ route('workspaces.projects.show',[$workspace,$project]) }}" style="font-weight: 700; font-size: 0.95rem;">
                                {{ $project->name }}
                            </a>
                        </td>
                        <td data-label="Trạng thái">
                            <x-status :value="$project->status" />
                            @if($project->archived_at)
                                <span class="badge" style="margin-left: 0.4rem; background: #fff1f2; color: #be123c;">Đã lưu trữ</span>
                            @endif
                        </td>
                        <td data-label="Công việc">
                            <span class="badge" style="font-family: var(--font-mono);">
                                {{ $project->tasks_count ?? 'Không có quyền xem' }} task
                            </span>
                        </td>
                        <td data-label="Hạn">
                            <span class="muted">{{ $project->deadline?->format('d/m/Y') ?? 'Chưa xác định' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 2rem;">
                            <p class="muted">Không có project nào phù hợp với bộ lọc hiện tại.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
@else
    <section class="grid" data-page="projects-index">
        @forelse($projects as $project)
            <article class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--primary); transition: transform 0.2s, box-shadow 0.2s;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                        <x-status :value="$project->status" />
                        @if($project->archived_at)
                            <span class="badge" style="background: #fff1f2; color: #be123c;">Đã lưu trữ</span>
                        @endif
                    </div>
                    <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">
                        <a href="{{ route('workspaces.projects.show',[$workspace,$project]) }}" style="text-decoration: none;">
                            {{ $project->name }}
                        </a>
                    </h2>
                    <div style="display: flex; gap: 0.75rem; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                        <span>📋 <strong>{{ $project->tasks_count ?? 'Không có quyền' }}</strong> tasks</span>
                        <span>📅 Hạn: <strong>{{ $project->deadline?->format('d/m/Y') ?? 'Chưa có' }}</strong></span>
                    </div>
                </div>
                <div style="padding-top: 1rem; border-top: 1px solid var(--border-light); margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                    <a href="{{ route('workspaces.projects.show',[$workspace,$project]) }}" class="button secondary" style="width: 100%; text-align: center;">
                        Mở chi tiết dự án ›
                    </a>
                </div>
            </article>
        @empty
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem 1.5rem;">
                <p style="font-size: 2rem; margin-bottom: 0.5rem;">📁</p>
                <h3>Chưa có dự án phù hợp</h3>
                <p class="muted">Hãy tạo dự án mới hoặc điều chỉnh tiêu chí tìm kiếm.</p>
                @if($canCreate)
                    <div style="margin-top: 1rem;">
                        <a class="button" href="{{ route('workspaces.projects.create',$workspace) }}">Tạo dự án mới</a>
                    </div>
                @endif
            </div>
        @endforelse
    </section>
@endif

<div style="margin-top: 1.5rem;">
    {{ $projects->links() }}
</div>
</x-layout>
