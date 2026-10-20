<x-layout title="Đăng ký">
<section class="card narrow" data-page="register">
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: var(--primary-gradient); color: #fff; border-radius: 12px; font-size: 1.5rem; margin-bottom: 0.75rem; box-shadow: var(--primary-glow);">⚡</div>
        <h1 style="margin-bottom: 0.35rem;">Đăng ký tài khoản</h1>
        <p class="muted">Hệ thống sẽ tự động khởi tạo Workspace cá nhân ngay sau khi đăng ký</p>
    </div>

    <form action="{{ route('register.store') }}" method="POST" data-form="register">
        @csrf
        <x-input name="name" label="Họ và tên" required maxlength="120" autocomplete="name" placeholder="Nguyễn Văn A" />
        <x-input name="email" label="Địa chỉ Email" type="email" required autocomplete="username" placeholder="name@example.com" />
        <x-input name="password" label="Mật khẩu (ít nhất 8 ký tự, gồm cả chữ và số)" type="password" required autocomplete="new-password" placeholder="••••••••" />
        <x-input name="password_confirmation" label="Xác nhận lại mật khẩu" type="password" required autocomplete="new-password" placeholder="••••••••" />
        <x-timezone-select :timezones="$timezones" />

        <div class="actions" style="margin-top: 1.5rem;">
            <button type="submit" style="width: 100%;">Tạo tài khoản & Bắt đầu</button>
        </div>

        <div style="text-align: center; margin-top: 1.25rem; font-size: 0.875rem;">
            <span class="muted">Đã có tài khoản?</span>
            <a href="{{ route('login') }}" style="font-weight: 600; margin-left: 0.35rem;">Đăng nhập ngay</a>
        </div>
    </form>
</section>
</x-layout>
