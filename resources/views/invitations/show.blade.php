<x-layout title="Lời mời tham gia workspace">
<div style="max-width: 520px; margin: 3rem auto;">
    <section class="card" data-page="invitation-show" style="padding: 2.25rem; text-align: center;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary), #4f46e5); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </div>

        <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; color: var(--color-text); letter-spacing: -0.01em;">
            Lời mời tham gia Workspace
        </h1>
        <p style="margin: 0 0 1.5rem; font-size: 0.9375rem; color: var(--color-text-muted);">
            Bạn đã được mời cộng tác vào workspace
        </p>

        <div style="background: var(--color-surface-hover); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem; text-align: left; margin-bottom: 1.75rem; display: flex; flex-direction: column; gap: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Workspace:</span>
                <strong style="font-size: 1rem; color: var(--color-text);">{{ $invitation['workspace_name'] }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Email người nhận:</span>
                <span style="font-size: 0.875rem; font-weight: 600; color: var(--color-primary);">{{ $invitation['email'] }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Vai trò được phân:</span>
                <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--color-primary); font-weight: 600; font-size: 0.8125rem; padding: 0.125rem 0.5rem; border-radius: var(--radius-sm);">
                    {{ $invitation['role_name'] }}
                </span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border); padding-top: 0.625rem;">
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Thời hạn hiệu lực:</span>
                <span style="font-size: 0.8125rem; font-weight: 500; color: #d97706;">
                    {{ \Carbon\CarbonImmutable::createFromTimestamp($invitation['expires_at'])->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>

        @auth
            @if(mb_strtolower(auth()->user()->email) === $invitation['email'])
                <form action="{{ route('invitations.accept', $token) }}" method="POST" data-form="invitation-accept">
                    @csrf
                    <button type="submit" class="button" style="width: 100%; padding: 0.75rem; font-size: 1rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Chấp nhận lời mời & Gia nhập
                    </button>
                </form>
            @else
                <div style="background: rgba(239, 68, 68, 0.08); border-left: 3px solid var(--color-danger); padding: 0.875rem 1rem; border-radius: var(--radius-sm); text-align: left; margin-bottom: 1.25rem;">
                    <p class="error" style="margin: 0; font-size: 0.875rem; color: #dc2626;">
                        ⚠️ Bạn đang đăng nhập bằng tài khoản <strong>{{ auth()->user()->email }}</strong>, khác với email nhận lời mời. Hãy đăng xuất và đăng nhập đúng email được mời.
                    </p>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="button secondary" style="width: 100%; padding: 0.625rem;">
                        Đăng xuất tài khoản hiện tại
                    </button>
                </form>
            @endif
        @else
            <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 1.25rem;">
                Vui lòng đăng nhập hoặc đăng ký tài khoản với đúng địa chỉ email trên để kích hoạt lời mời.
            </p>
            <div class="actions" style="display: flex; gap: 0.75rem;">
                <a class="button" href="{{ route('login') }}" style="flex: 1; padding: 0.625rem;">
                    Đăng nhập
                </a>
                <a class="button secondary" href="{{ route('register') }}" style="flex: 1; padding: 0.625rem;">
                    Đăng ký mới
                </a>
            </div>
        @endauth
    </section>
</div>
</x-layout>
