<x-layout title="Đặt lại mật khẩu">
<section class="card narrow" data-page="reset-password"><h1>Đặt lại mật khẩu</h1>
<p>Mật khẩu có ít nhất 8 ký tự, gồm chữ và số. Đổi thành công sẽ kết thúc các phiên đăng nhập cũ.</p>
<form method="post" action="{{ route('password.reset.store') }}" data-form="reset-password">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<x-input name="email" type="email" label="Email đã đăng ký" :value="$email" required autocomplete="username" maxlength="255" />
<x-input name="password" type="password" label="Mật khẩu mới" required autocomplete="new-password" minlength="8" maxlength="200" />
<x-input name="password_confirmation" type="password" label="Nhập lại mật khẩu mới" required autocomplete="new-password" minlength="8" maxlength="200" />
<div class="actions"><button>Lưu mật khẩu mới</button><a href="{{ route('password.request') }}">Yêu cầu liên kết mới</a></div>
</form></section></x-layout>
