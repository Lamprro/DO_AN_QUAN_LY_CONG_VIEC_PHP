<x-layout title="Hồ sơ cá nhân">
<div class="page-heading" style="margin-bottom: 1.5rem;">
    <div>
        <p class="eyebrow">TÀI KHOẢN CỦA BẠN</p>
        <h1 style="margin: 0;">Hồ sơ cá nhân</h1>
        <p class="muted" style="margin-top: 0.25rem;">Quản lý thông tin tài khoản, múi giờ hiển thị và bảo mật mật khẩu</p>
    </div>
</div>

<div class="grid" data-page="profile" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr)) !important; gap: 1.5rem;">
    <!-- Personal Information Card -->
    <section class="card" style="margin: 0; border-top: 4px solid var(--primary);">
        <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">👤 Thông tin tài khoản</h2>
        <p class="muted" style="font-size: 0.85rem; margin-bottom: 1.25rem;">Cập nhật họ tên, địa chỉ email và múi giờ làm việc mặc định của bạn.</p>

        <form action="{{ route('profile.update') }}" method="POST" data-form="profile">
            @csrf
            @method('PATCH')
            <x-input name="name" label="Họ và tên" :value="$user->name" required maxlength="120" placeholder="Nguyễn Văn A" />
            <x-input name="email" label="Địa chỉ Email" type="email" :value="$user->email" required placeholder="name@example.com" />
            <x-timezone-select :timezones="$timezones" :value="$user->timezone" />
            <x-input id="profile-current-password" name="current_password" label="Mật khẩu hiện tại (bắt buộc nếu bạn thay đổi email)" type="password" autocomplete="current-password" placeholder="••••••••" />

            <div style="margin-top: 1.5rem;">
                <button type="submit">Lưu thông tin hồ sơ</button>
            </div>
        </form>
    </section>

    <!-- Password Change Card -->
    <section class="card" style="margin: 0; border-top: 4px solid #f59e0b;">
        <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">🔒 Đổi mật khẩu</h2>
        <p class="muted" style="font-size: 0.85rem; margin-bottom: 1.25rem;">Nên sử dụng mật khẩu mạnh gồm chữ hoa, chữ thường, số và ký tự đặc biệt.</p>

        <form action="{{ route('password.update') }}" method="POST" data-form="password">
            @csrf
            @method('PUT')
            <x-input id="password-current-password" name="current_password" label="Mật khẩu hiện tại" type="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input name="password" label="Mật khẩu mới (ít nhất 8 ký tự, gồm cả chữ và số)" type="password" required autocomplete="new-password" placeholder="••••••••" />
            <x-input name="password_confirmation" label="Xác nhận lại mật khẩu mới" type="password" required autocomplete="new-password" placeholder="••••••••" />

            <div style="margin-top: 1.5rem;">
                <button type="submit" style="background: var(--warning) !important;">Đổi mật khẩu</button>
            </div>
        </form>
    </section>
</div>
</x-layout>
