<x-layout title="Vai trò — {{ $workspace->name }}">
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.375rem;">
            <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary); font-weight: 500;">{{ $workspace->name }}</a>
            <span style="margin: 0 0.375rem;">/</span>
            <span>Vai trò & Phân quyền</span>
        </div>
        <h1 style="font-size: 1.625rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.02em;">
            Quản lý vai trò (RBAC)
        </h1>
        <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-muted);">
            Định nghĩa và quản lý các quyền hạn chi tiết cho thành viên trong workspace
        </p>
    </div>
    <div class="actions" style="display: flex; gap: 0.5rem; align-items: center;">
        <a class="button secondary" href="{{ route('workspaces.show', $workspace) }}">
            Về workspace
        </a>
        <a class="button" href="{{ route('workspaces.roles.create', $workspace) }}" style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tạo vai trò mới
        </a>
    </div>
</div>

<section class="card" data-page="roles-index" style="padding: 1.5rem;">
    <div class="table-wrap" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
            <thead>
                <tr>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); border-bottom: 1px solid var(--color-border); width: 220px;">Vai trò</th>
                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); border-bottom: 1px solid var(--color-border);">Danh mục quyền</th>
                    <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); border-bottom: 1px solid var(--color-border); width: 200px;">Đang sử dụng</th>
                    <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); border-bottom: 1px solid var(--color-border); width: 140px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                <tr data-role-id="{{ $role->id }}" style="border-bottom: 1px solid var(--color-border); transition: background-color var(--transition-fast);">
                    <td style="padding: 1rem; vertical-align: top;">
                        <div style="font-weight: 700; color: var(--color-text); font-size: 0.9375rem;">
                            {{ $role->name }}
                        </div>
                        @if($role->is_system)
                            <div style="margin-top: 0.25rem;">
                                <span class="badge" style="background: rgba(100, 116, 139, 0.12); color: var(--color-text-muted); font-size: 0.6875rem; padding: 0.125rem 0.375rem; border-radius: var(--radius-sm); font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Hệ thống
                                </span>
                            </div>
                        @else
                            <div style="margin-top: 0.25rem; font-size: 0.75rem; color: var(--color-text-muted);">Tùy chỉnh</div>
                        @endif
                    </td>
                    <td style="padding: 1rem; vertical-align: top;">
                        <div style="display: flex; flex-wrap: wrap; gap: 0.3125rem; max-width: 600px;">
                            @foreach($role->permissions as $perm)
                                <span class="badge" style="background: var(--color-surface-hover); border: 1px solid var(--color-border); font-size: 0.6875rem; color: var(--color-text); padding: 0.125rem 0.4375rem; border-radius: var(--radius-sm); font-family: var(--font-mono);">
                                    {{ $perm }}
                                </span>
                            @endforeach
                        </div>
                        <div style="margin-top: 0.375rem; font-size: 0.75rem; color: var(--color-text-muted);">
                            Tổng: <strong>{{ count($role->permissions) }}</strong> quyền
                        </div>
                    </td>
                    <td style="padding: 1rem; vertical-align: top; text-align: center;">
                        <div style="display: inline-flex; flex-direction: column; gap: 0.25rem; align-items: center;">
                            <span class="badge" style="background: rgba(99, 102, 241, 0.08); color: var(--color-primary); font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                                {{ $role->workspace_members_count }} workspace
                            </span>
                            <span class="badge" style="background: rgba(16, 185, 129, 0.08); color: var(--color-success); font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                                {{ $role->project_members_count }} project
                            </span>
                        </div>
                    </td>
                    <td style="padding: 1rem; vertical-align: top; text-align: right;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                            @can('update', $role)
                                <a class="button secondary" href="{{ route('workspaces.roles.edit', [$workspace, $role]) }}" style="padding: 0.25rem 0.625rem; font-size: 0.75rem;">
                                    Sửa
                                </a>
                            @endcan
                            @can('delete', $role)
                                <form class="inline" action="{{ route('workspaces.roles.destroy', [$workspace, $role]) }}" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa vai trò này?');" style="margin: 0;">
                                    @csrf @method('DELETE')
                                    <button class="button danger" type="submit" style="padding: 0.25rem 0.625rem; font-size: 0.75rem;">
                                        Xóa
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
</x-layout>
