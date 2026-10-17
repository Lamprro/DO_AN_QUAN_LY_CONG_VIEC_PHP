<x-layout title="Đăng ký"><section class="card narrow" data-page="register"><h1>Đăng ký tài khoản</h1><p class="muted">Sau khi đăng ký, hệ thống tạo workspace cá nhân cho bạn.</p>
<form action="{{ route('register.store') }}" method="POST" data-form="register">@csrf
<x-input name="name" label="Họ tên" required maxlength="120" autocomplete="name" />
<x-input name="email" label="Email" type="email" required autocomplete="username" />
<x-input name="password" label="Mật khẩu (ít nhất 8 ký tự, có chữ và số)" type="password" required autocomplete="new-password" />
<x-input name="password_confirmation" label="Nhập lại mật khẩu" type="password" required autocomplete="new-password" />
<x-timezone-select :timezones="$timezones" />
<button type="submit">Đăng ký</button></form></section></x-layout>
