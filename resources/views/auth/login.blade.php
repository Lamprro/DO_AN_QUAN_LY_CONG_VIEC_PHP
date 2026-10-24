<x-layout title="Đăng nhập">
<section class="card narrow" data-page="login">
    <div style="text-align: center; margin-bottom: 1.5rem;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: var(--primary-gradient); color: #fff; border-radius: 12px; font-size: 1.5rem; margin-bottom: 0.75rem; box-shadow: var(--primary-glow);">⚡</div>
        <h1 style="margin-bottom: 0.35rem;">Đăng nhập hệ thống</h1>
        <p class="muted">Quản lý công việc, tiến độ dự án và phân tích nghiệp vụ</p>
    </div>

    @if(app()->environment('local'))
    <div class="demo-helper-box">
        <p>⚡ Tài khoản trải nghiệm nhanh</p>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div style="font-size: 0.825rem; color: var(--text);">
                <code>demo1@example.test</code> · <code>Demo@123456</code>
            </div>
            <button type="button" class="demo-helper-btn" onclick="document.getElementById('email').value='demo1@example.test'; document.getElementById('password').value='Demo@123456';">Điền nhanh</button>
        </div>
    </div>

    @endif
    <form action="{{ route('login.store') }}" method="POST" data-form="login">
        @csrf
        <x-input name="email" label="Địa chỉ Email" type="email" required autocomplete="username" placeholder="name@example.com" />
        <x-input name="password" label="Mật khẩu" type="password" required autocomplete="current-password" placeholder="••••••••" />

        <div style="display: flex; justify-content: space-between; align-items: center; margin: 1rem 0;">
            <label style="margin: 0; font-weight: 500; cursor: pointer;">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))> Ghi nhớ đăng nhập
            </label>
            <a href="{{ route('password.request') }}">Quên mật khẩu?</a>
        </div>

        <div class="actions" style="margin-top: 1.25rem;">
            <button type="submit" style="width: 100%;">Đăng nhập</button>
        </div>

        <div style="text-align: center; margin-top: 1.25rem; font-size: 0.875rem;">
            <span class="muted">Chưa có tài khoản?</span>
            <a href="{{ route('register') }}" style="font-weight: 600; margin-left: 0.35rem;">Tạo tài khoản mới</a>
        </div>
    </form>
</section>
</x-layout>
