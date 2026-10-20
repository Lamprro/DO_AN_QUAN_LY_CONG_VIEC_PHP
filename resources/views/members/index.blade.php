<x-layout title="Thành viên — {{ $workspace->name }}">
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.375rem;">
            <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary); font-weight: 500;">{{ $workspace->name }}</a>
            <span style="margin: 0 0.375rem;">/</span>
            <span>Thành viên</span>
        </div>
        <h1 style="font-size: 1.625rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.02em;">
            Quản lý thành viên
        </h1>
        <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-muted);">
            Tổng cộng {{ $members->count() }} nhân sự đang tham gia workspace
        </p>
    </div>
    <div class="actions" style="display: flex; gap: 0.5rem;">
        <a class="button secondary" href="{{ route('workspaces.show', $workspace) }}" style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Về workspace
        </a>
    </div>
</div>

@if(session('invitation_url'))
<div class="notice link-box" data-invitation-url="{{ session('invitation_url') }}" style="background: rgba(16, 185, 129, 0.08); border-left: 4px solid var(--color-success); padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; color: var(--color-success); font-weight: 600;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <span>Liên kết lời mời đã được tạo thành công!</span>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; background: var(--color-surface); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
        <code style="flex: 1; word-break: break-all; font-family: var(--font-mono); font-size: 0.875rem; color: var(--color-primary);">{{ session('invitation_url') }}</code>
        <a class="button" href="{{ session('invitation_url') }}" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem;">Sao chép / Mở link</a>
    </div>
    <p class="muted" style="margin: 0.5rem 0 0; font-size: 0.8125rem; color: var(--color-text-muted);">
        Chỉ chia sẻ đường dẫn này cho đúng người nhận được mời. Người nhận cần đăng nhập bằng email mời.
    </p>
</div>
@endif

<div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
    {{-- Danh sách thành viên --}}
    <section class="card" data-page="members-index" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Thành viên hiện tại ({{ $members->count() }})
            </h2>
        </div>

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
                    <tr data-member-id="{{ $member->id }}" style="border-bottom: 1px solid var(--color-border); transition: background-color var(--transition-fast);">
                        <td style="padding: 1rem; vertical-align: middle;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), #6366f1); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; flex-shrink: 0; box-shadow: var(--shadow-sm);">
                                    {{ strtoupper(mb_substr($member->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--color-text); font-size: 0.9375rem; display: flex; align-items: center; gap: 0.5rem;">
                                        {{ $member->user->name }}
                                        @if($member->user_id === $workspace->owner_id)
                                            <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; font-size: 0.6875rem; padding: 0.125rem 0.5rem; border-radius: 9999px; font-weight: 700;">Owner</span>
                                        @endif
                                        @if($member->user_id === auth()->id())
                                            <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--color-primary); font-size: 0.6875rem; padding: 0.125rem 0.5rem; border-radius: 9999px;">Bạn</span>
                                        @endif
                                    </div>
                                    <small style="color: var(--color-text-muted); font-size: 0.8125rem;">{{ $member->user->email }}</small>
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
                            @if($member->user_id !== $workspace->owner_id && $member->user_id !== auth()->id() && !array_diff($member->role->permissions, $actorPermissions))
                                <div style="display: flex; flex-direction: column; gap: 0.75rem; align-items: flex-end;">
                                    <form action="{{ route('workspaces.members.update', [$workspace, $member]) }}" method="POST" data-form="member-update" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end;">
                                        @csrf @method('PATCH')
                                        <select id="role-{{ $member->id }}" name="role_id" style="padding: 0.3125rem 0.625rem; font-size: 0.8125rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                                            @foreach($assignableRoles as $role)
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
                                    <form action="{{ route('workspaces.members.destroy', [$workspace, $member]) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn gỡ thành viên này khỏi workspace?');" style="margin: 0;">
                                        @csrf @method('DELETE')
                                        <button class="button danger" type="submit" style="padding: 0.25rem 0.625rem; font-size: 0.75rem;">
                                            Gỡ khỏi workspace
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

    {{-- Phần Mời thành viên & Lời mời đang chờ --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 1.5rem;">
        @if($workspace->kind->value === 'team')
        <section class="card" style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--color-text);">Mời thành viên mới</h2>
            </div>
            <form action="{{ route('workspaces.invitations.store', $workspace) }}" method="POST" data-form="invitation-create" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf
                <x-input name="email" label="Email người nhận" type="email" placeholder="dongnghiep@example.com" required />

                <div class="field">
                    <label for="invite-role" style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Vai trò ban đầu</label>
                    <select id="invite-role" name="role_id" required style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                        @foreach($assignableRoles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id') === $role->id)>
                                {{ \App\Support\UiLabels::text($role->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-top: 0.5rem;">
                    <button type="submit" class="button" style="width: 100%; display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 0.625rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Tạo liên kết lời mời
                    </button>
                </div>
            </form>
            <p class="muted" style="margin: 0.875rem 0 0; font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.5;">
                ℹ️ Liên kết mời có hiệu lực <strong>{{ config('workspace.invitation_ttl_minutes') }} phút</strong> và cần được chấp nhận bằng đúng tài khoản email người nhận.
            </p>
        </section>
        @endif

        {{-- Lời mời đang chờ --}}
        <section class="card" style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-warning);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--color-text);">Lời mời đang chờ ({{ count($pendingInvitations) }})</h2>
            </div>

            @forelse($pendingInvitations as $invitation)
                <div style="background: var(--color-surface-hover); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.875rem 1rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text); font-size: 0.9375rem;">
                            {{ $invitation['email'] }}
                        </div>
                        <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-top: 0.125rem;">
                            Vai trò: <span style="font-weight: 500; color: var(--color-primary);">{{ $invitation['role_name'] }}</span> · Hết hạn: {{ \Carbon\CarbonImmutable::createFromTimestamp($invitation['expires_at'])->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i') }}
                        </div>
                    </div>
                    <form action="{{ route('workspaces.invitations.destroy', [$workspace, $invitation['id']]) }}" method="POST" style="margin: 0;">
                        @csrf @method('DELETE')
                        <button class="button danger" type="submit" style="padding: 0.3125rem 0.625rem; font-size: 0.75rem;">
                            Thu hồi
                        </button>
                    </form>
                </div>
            @empty
                <div style="text-align: center; padding: 2rem 1rem; color: var(--color-text-muted);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem; opacity: 0.6;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <p style="margin: 0; font-size: 0.875rem;">Không có lời mời nào đang chờ xử lý.</p>
                </div>
            @endforelse
        </section>
    </div>
</div>
</x-layout>
