<x-layout title="Hồ sơ"><h1>Hồ sơ cá nhân</h1><div class="grid" data-page="profile">
<section class="card"><h2>Thông tin tài khoản</h2><form action="{{ route('profile.update') }}" method="POST" data-form="profile">@csrf @method('PATCH')
<x-input name="name" label="Họ tên" :value="$user->name" required maxlength="120" />
<x-input name="email" label="Email" type="email" :value="$user->email" required />
<x-timezone-select :timezones="$timezones" :value="$user->timezone" />
<x-input id="profile-current-password" name="current_password" label="Mật khẩu hiện tại (bắt buộc nếu đổi email)" type="password" autocomplete="current-password" />
<button type="submit">Lưu hồ sơ</button></form></section>
<section class="card"><h2>Đổi mật khẩu</h2><form action="{{ route('password.update') }}" method="POST" data-form="password">@csrf @method('PUT')
<x-input id="password-current-password" name="current_password" label="Mật khẩu hiện tại" type="password" required autocomplete="current-password" />
<x-input name="password" label="Mật khẩu mới (ít nhất 8 ký tự, có chữ và số)" type="password" required autocomplete="new-password" />
<x-input name="password_confirmation" label="Xác nhận mật khẩu mới" type="password" required autocomplete="new-password" />
<button type="submit">Đổi mật khẩu</button></form></section></div></x-layout>
