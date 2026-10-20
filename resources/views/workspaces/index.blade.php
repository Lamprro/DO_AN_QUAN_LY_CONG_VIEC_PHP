<x-layout title="Danh sách Workspace">
<div class="page-heading" style="margin-bottom: 1.5rem;">
    <div>
        <p class="eyebrow">QUẢN LÝ KHÔNG GIAN LÀM VIỆC</p>
        <h1 style="margin: 0;">Workspace của bạn</h1>
        <p class="muted" style="margin-top: 0.25rem;">Chọn không gian làm việc để quản lý dự án, công việc và theo dõi tiến độ</p>
    </div>
    <div class="actions">
        <a class="button" href="{{ route('workspaces.create') }}">
            <span>+</span> Tạo workspace nhóm
        </a>
    </div>
</div>

<form action="{{ route('workspaces.index') }}" method="GET" class="card" data-form="workspace-search" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <label for="q">Tìm kiếm theo tên workspace</label>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <input id="q" name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" maxlength="160" placeholder="Nhập tên workspace..." style="flex: 1; min-width: 200px;">
        <button type="submit">Tìm kiếm</button>
        <a href="{{ route('workspaces.index') }}" class="button secondary">Bỏ lọc</a>
    </div>
</form>

<section data-page="workspaces-index">
    <div class="grid">
        @forelse($workspaces as $workspace)
            <article class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--primary); transition: transform 0.2s, box-shadow 0.2s;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span class="badge" style="{{ $workspace->kind->value === 'team' ? 'background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;' : 'background: #f1f5f9; color: #475569;' }}">
                            {{ $workspace->kind->value === 'team' ? '🏢 Nhóm / Team' : '👤 Cá nhân' }}
                        </span>
                        <small class="muted" style="font-family: var(--font-mono); font-size: 0.75rem;">{{ $workspace->timezone }}</small>
                    </div>
                    <h2 style="font-size: 1.2rem; margin-bottom: 0.5rem;">
                        <a href="{{ route('workspaces.show',$workspace) }}" style="text-decoration: none;">{{ $workspace->name }}</a>
                    </h2>
                    <p class="muted" style="font-size: 0.85rem; margin-bottom: 1rem;">
                        Múi giờ: <strong>{{ $workspace->timezone }}</strong>
                    </p>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border-light); margin-top: auto;">
                    <a href="{{ route('workspaces.show',$workspace) }}" style="font-size: 0.85rem; font-weight: 600;">Xem chi tiết ›</a>
                    <form action="{{ route('workspaces.select',$workspace) }}" method="POST" style="margin: 0;">
                        @csrf
                        <button class="secondary" type="submit" style="min-height: 34px; padding: 0.35rem 0.85rem; font-size: 0.8rem;">Vào workspace</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem 1.5rem;">
                <p style="font-size: 2rem; margin-bottom: 0.5rem;">📁</p>
                <h3>Không tìm thấy workspace phù hợp</h3>
                <p class="muted">Hãy thử từ khóa khác hoặc tạo workspace mới để bắt đầu.</p>
                <div style="margin-top: 1rem;">
                    <a class="button" href="{{ route('workspaces.create') }}">Tạo workspace nhóm mới</a>
                </div>
            </div>
        @endforelse
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $workspaces->links() }}
    </div>
</section>
</x-layout>
