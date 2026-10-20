<x-layout title="Thành viên — {{ $project->name }}">
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.375rem;">
            <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary); font-weight: 500;">{{ $workspace->name }}</a>
            <span style="margin: 0 0.375rem;">/</span>
            <a href="{{ route('workspaces.projects.show', [$workspace, $project]) }}" style="color: var(--color-primary); font-weight: 500;">{{ $project->name }}</a>
            <span style="margin: 0 0.375rem;">/</span>
            <span>Thành viên</span>
        </div>
        <h1 style="font-size: 1.625rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.02em;">
            Thành viên dự án: {{ $project->name }}
        </h1>
        <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-muted);">
            Quản trị nhân sự và quyền hạn tham gia trực tiếp trong dự án này
        </p>
    </div>
    <div class="actions" style="display: flex; gap: 0.5rem;">
        <a class="button secondary" href="{{ route('workspaces.projects.show', [$workspace, $project]) }}" style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Về dự án
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
    {{-- Thêm thành viên từ workspace --}}
    @if($candidates->isNotEmpty())
    <section class="card" style="padding: 1.5rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md);">
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--color-text);">Thêm thành viên từ workspace</h2>
        </div>
        <form method="POST" action="{{ route('workspaces.projects.members.store', [$workspace, $project]) }}" data-form="project-member-create" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)) auto; gap: 1rem; align-items: flex-end;">
            @csrf
            <div class="field" style="margin: 0;">
                <label for="workspace_member_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Chọn nhân sự workspace</label>
                <select name="workspace_member_id" id="workspace_member_id" required style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    <option value="">-- Chọn thành viên --</option>
                    @foreach($candidates as $candidate)
                        <option value="{{ $candidate->id }}" @selected(old('workspace_member_id') === $candidate->id)>
                            {{ $candidate->user->name }} ({{ $candidate->user->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin: 0;">
                <label for="role_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Vai trò trong dự án</label>
                <select name="role_id" id="role_id" required style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id') === $role->id)>
                            {{ \App\Support\UiLabels::text($role->name) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="status" value="active">
            <button type="submit" class="button" style="padding: 0.5625rem 1.25rem; font-weight: 600; height: fit-content; display: inline-flex; align-items: center; gap: 0.375rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Thêm vào dự án
            </button>
        </form>
    </section>
    @endif

    {{-- Danh sách thành viên trong project --}}
    <section class="card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 1.25rem; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Nhân sự tham gia dự án ({{ $members->count() }})
        </h2>

        <div class="table-wrap" style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); border-bottom: 1px solid var(--color-border);">Thành viên</th>
                        <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); border-bottom: 1px solid var(--color-border);">Vai trò & Trạng thái</th>
                        <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); border-bottom: 1px solid var(--color-border); min-width: 280px;">Thao tác quản trị</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                    <tr data-project-member-id="{{ $member->id }}" style="border-bottom: 1px solid var(--color-border); transition: background-color var(--transition-fast);">
                        <td style="padding: 1rem; vertical-align: middle;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #3b82f6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; flex-shrink: 0; box-shadow: var(--shadow-sm);">
                                    {{ strtoupper(mb_substr($member->workspaceMember->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--color-text); font-size: 0.9375rem;">
                                        {{ $member->workspaceMember->user->name }}
                                        @if($member->workspaceMember->user_id === auth()->id())
                                            <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--color-primary); font-size: 0.6875rem; padding: 0.125rem 0.5rem; border-radius: 9999px;">Bạn</span>
                                        @endif
                                    </div>
                                    <small style="color: var(--color-text-muted); font-size: 0.8125rem;">
                                        {{ $member->workspaceMember->user->email }} · workspace: <span style="font-weight: 500;">{{ $member->workspaceMember->status->value }}</span>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 1rem; vertical-align: middle;">
                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                <div style="font-weight: 600; font-size: 0.875rem; color: var(--color-text);">
                                    {{ \App\Support\UiLabels::text($member->role->name) }}
                                </div>
                                <div>
                                    @if($member->status->value === 'active')
                                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: var(--radius-sm); font-weight: 500;">
                                            ● Hoạt động
                                        </span>
                                    @elseif($member->status->value === 'suspended')
                                        <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: var(--radius-sm); font-weight: 500;">
                                            ● Tạm ngưng
                                        </span>
                                    @else
                                        <span class="badge" style="background: rgba(100, 116, 139, 0.15); color: #475569; font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: var(--radius-sm); font-weight: 500;">
                                            {{ \App\Support\UiLabels::text($member->status) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="padding: 1rem; vertical-align: middle; text-align: right;">
                            @if(!in_array($member->workspaceMember->user_id, [$workspace->owner_id, auth()->id()], true) && !array_diff($member->role->permissions, $actorPermissions))
                                <div style="display: flex; flex-direction: column; gap: 0.75rem; align-items: flex-end;">
                                    <form method="POST" action="{{ route('workspaces.projects.members.update', [$workspace, $project, $member]) }}" data-form="project-member-update" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end;">
                                        @csrf @method('PATCH')
                                        <select id="role-{{ $member->id }}" name="role_id" style="padding: 0.3125rem 0.625rem; font-size: 0.8125rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}" @selected($member->role_id === $role->id)>
                                                    {{ \App\Support\UiLabels::text($role->name) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <select id="status-{{ $member->id }}" name="status" style="padding: 0.3125rem 0.625rem; font-size: 0.8125rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                                            @foreach(['active', 'suspended', 'left'] as $status)
                                                <option value="{{ $status }}" @selected($member->status->value === $status)>
                                                    {{ \App\Support\UiLabels::text($status) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="button" style="padding: 0.3125rem 0.75rem; font-size: 0.8125rem;">
                                            Lưu
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('workspaces.projects.members.destroy', [$workspace, $project, $member]) }}" data-form="project-member-delete" data-confirm="Gỡ thành viên khỏi project này?" style="margin: 0;">
                                        @csrf @method('DELETE')
                                        <button class="button danger" type="submit" style="padding: 0.25rem 0.625rem; font-size: 0.75rem;">
                                            Gỡ khỏi project
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="muted" style="font-size: 0.8125rem; color: var(--color-text-muted); font-style: italic;">
                                    Được bảo vệ hoặc ngoài phạm vi quyền
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
</x-layout>
