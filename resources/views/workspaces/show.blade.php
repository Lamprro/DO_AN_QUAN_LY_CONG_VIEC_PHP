<x-layout :title="$workspace->name">
<section class="card" data-page="workspace-show" data-workspace-id="{{ $workspace->id }}" style="border-top: 4px solid var(--primary);">
    <div class="page-heading">
        <div>
            <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                <span class="badge" style="{{ $workspace->kind->value === 'team' ? 'background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;' : 'background: #f1f5f9; color: #475569;' }}">
                    {{ \App\Support\UiLabels::text($workspace->kind) }}
                </span>
                <span class="badge" style="background: var(--primary-light); color: var(--primary); border-color: rgba(99, 102, 241, 0.2);">
                    Vai trò: <strong>{{ \App\Support\UiLabels::text($membership->role->name) }}</strong>
                </span>
            </div>
            <h1 style="margin-bottom: 0.35rem;">{{ $workspace->name }}</h1>
            <p class="muted" style="margin: 0;">
                Chủ sở hữu: <strong>{{ $workspace->owner->name }}</strong> · Múi giờ: <strong>{{ $workspace->timezone }}</strong>
            </p>
        </div>
    </div>

    <!-- KPI Quick Stats Grid -->
    <div class="kpi-grid" style="margin: 1.5rem 0;">
        <article class="kpi">
            <span>👥 Thành viên</span>
            <strong>{{ $workspace->members_count }}</strong>
        </article>
        <article class="kpi">
            <span>🛡️ Vai trò</span>
            <strong>{{ $workspace->roles_count }}</strong>
        </article>
        <article class="kpi">
            <span>📁 Projects</span>
            <strong>{{ $workspace->projects_count }}</strong>
        </article>
        <article class="kpi">
            <span>📋 Công việc</span>
            <strong>{{ $workspace->tasks_count }}</strong>
        </article>
    </div>

    <!-- Quick Navigation Hub -->
    <div style="padding-top: 1rem; border-top: 1px solid var(--border);">
        <h3 style="font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.75rem;">Điều hướng chức năng</h3>
        <div class="actions">
            <a class="button" href="{{ route('workspaces.projects.index',$workspace) }}">
                📁 Quản lý Projects
            </a>
            @if(app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project ?? null))
                <a class="button secondary" href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>($project ?? null)?->id]) }}">
                    📊 Kế hoạch
                </a>
            @endif
            @if($canViewCalendar)
                <a class="button secondary" href="{{ route('workspaces.calendar.index',$workspace) }}">
                    📅 Lịch & Timeline
                </a>
            @endif
            @if($canViewReport)
                <a class="button secondary" href="{{ route('workspaces.dashboard',$workspace) }}">
                    📈 Dashboard / Báo cáo
                </a>
            @endif
            @if($workspace->kind->value==='personal')
                <a class="button" href="{{ route('workspaces.tasks.index',$workspace) }}">
                    👤 Công việc cá nhân
                </a>
            @endif
            @can('manageMembers',$workspace)
                <a class="button secondary" href="{{ route('workspaces.members.index',$workspace) }}">
                    👥 Thành viên
                </a>
            @endcan
            @can('manageRoles',$workspace)
                <a class="button secondary" href="{{ route('workspaces.roles.index',$workspace) }}">
                    🛡️ Phân vai trò
                </a>
            @endcan
            @can('update',$workspace)
                <a class="button secondary" href="{{ route('workspaces.edit',$workspace) }}">
                    ⚙️ Sửa workspace
                </a>
            @endcan
        </div>
    </div>
</section>

<!-- Administrative & Ownership Sections -->
@if($workspace->owner_id === auth()->id() && $workspace->kind->value === 'team')
    <details class="card" style="border-left: 4px solid var(--warning);">
        <summary style="font-weight: 700; cursor: pointer; font-size: 1.05rem;">Chuyển quyền chủ sở hữu (Owner Transfer)</summary>
        <p class="muted" style="margin-top: 0.75rem;">Chủ cũ sẽ chuyển thành Manager. Quyền quản trị từng project con vẫn được giữ độc lập theo phân công.</p>
        <form action="{{ route('workspaces.transfer',$workspace) }}" method="POST" data-form="owner-transfer" data-confirm="Chuyển chủ sở hữu? Chủ cũ sẽ thành Quản lý; quyền từng project vẫn độc lập.">
            @csrf
            <div class="field">
                <label for="member_id">Chọn thành viên đang hoạt động</label>
                <select id="member_id" name="member_id" required>
                    <option value="">-- Chọn thành viên tiếp nhận --</option>
                    @foreach($transferCandidates as $candidate)
                        <option value="{{ $candidate->id }}">{{ $candidate->user->name }} — {{ $candidate->user->email }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background: var(--warning) !important;">Xác nhận chuyển chủ sở hữu</button>
        </form>
    </details>

    <details class="card" style="border-left: 4px solid var(--danger);">
        <summary style="font-weight: 700; cursor: pointer; font-size: 1.05rem; color: var(--danger);">Xóa workspace trống</summary>
        <p class="muted" style="margin-top: 0.75rem;">Chỉ xóa được khi chưa có dữ liệu nghiệp vụ (task, project) và không còn lời mời nào đang chờ xử lý.</p>
        <form action="{{ route('workspaces.destroy',$workspace) }}" method="POST" data-form="workspace-delete" data-confirm="Xóa workspace trống này? Thao tác này không thể hoàn tác.">
            @csrf
            @method('DELETE')
            <button class="danger" type="submit">Xóa vĩnh viễn workspace này</button>
        </form>
    </details>
@elseif($workspace->owner_id !== auth()->id())
    <section class="card" style="border-left: 4px solid var(--danger);">
        <h3 style="color: var(--danger);">Rời khỏi Workspace</h3>
        <p class="muted">Sau khi rời, bạn sẽ mất toàn bộ quyền truy cập vào các dự án và công việc trong workspace này.</p>
        <form action="{{ route('workspaces.leave',$workspace) }}" method="POST" data-form="workspace-leave" data-confirm="Rời workspace này? Bạn sẽ mất quyền truy cập qua membership hiện tại.">
            @csrf
            <button class="danger" type="submit">Rời workspace</button>
        </form>
    </section>
@endif
</x-layout>
